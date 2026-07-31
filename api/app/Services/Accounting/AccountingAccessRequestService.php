<?php

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateAccessRequestDTO;
use App\Enums\Accounting\AccountingAccessStatus;
use App\Events\Accounting\AccountingAccessRequested;
use App\Exceptions\AccountingAccessException;
use App\Models\Accounting\AccountingOffice;
use App\Models\Accounting\AccountingOfficeTenant;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lado do CONTADOR do fluxo de vínculo (roadmap 2C): o escritório solicita
 * acesso a um tenant informando o CNPJ dele. Fluxo INVERSO ao convite de
 * funcionário — quem inicia é o contador, quem aprova é o dono do tenant
 * (AccountingAccessApprovalService).
 */
class AccountingAccessRequestService
{
    public function request(AccountingOffice $office, CreateAccessRequestDTO $dto): AccountingOfficeTenant
    {
        return DB::transaction(function () use ($office, $dto) {
            $tenant = Tenant::where('cnpj', $dto->tenantCnpj)
                ->where('is_active', true)
                ->first();

            if (!$tenant) {
                throw new AccountingAccessException(__('messages.accounting_access.tenant_not_found'));
            }

            $link = AccountingOfficeTenant::where('accounting_office_id', $office->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($link && $link->status === AccountingAccessStatus::Approved->value) {
                throw new AccountingAccessException(__('messages.accounting_access.already_approved'));
            }

            if ($link && $link->status === AccountingAccessStatus::Pending->value) {
                throw new AccountingAccessException(__('messages.accounting_access.already_pending'));
            }

            if (!$link) {
                $link = new AccountingOfficeTenant([
                    'accounting_office_id' => $office->id,
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Re-solicitação após revogação reusa a mesma linha (volta a pending).
            $link->fill([
                'status' => AccountingAccessStatus::Pending->value,
                'scopes' => null,
                'requested_at' => now(),
                'approved_at' => null,
                'revoked_at' => null,
                'approved_by' => null,
            ])->save();

            event(new AccountingAccessRequested(
                linkUuid: $link->uuid,
                accountingOfficeUuid: $office->uuid,
                tenantId: $tenant->id,
            ));

            return $link->fresh(['tenant']);
        });
    }

    public function myLinks(AccountingOffice $office): Collection
    {
        return AccountingOfficeTenant::with('tenant')
            ->where('accounting_office_id', $office->id)
            ->orderByDesc('id')
            ->get();
    }
}
