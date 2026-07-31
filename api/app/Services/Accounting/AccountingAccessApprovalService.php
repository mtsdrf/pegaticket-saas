<?php

namespace App\Services\Accounting;

use App\DTOs\Accounting\ApproveAccessDTO;
use App\Enums\Accounting\AccountingAccessStatus;
use App\Events\Accounting\AccountingAccessApproved;
use App\Events\Accounting\AccountingAccessRevoked;
use App\Exceptions\AccountingAccessException;
use App\Models\Accounting\AccountingOfficeTenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lado do TENANT do fluxo de vínculo (roadmap 2C): o dono do tenant lista as
 * solicitações e aprova (concedendo escopos) ou revoga. Todo acesso é escopado
 * ao tenant ativo — guard de posse por tenant_id (404 se o vínculo não
 * pertence ao tenant), mesmo padrão dos demais recursos tenant-scoped.
 */
class AccountingAccessApprovalService
{
    public function listForTenant(int $tenantId): Collection
    {
        return AccountingOfficeTenant::with('accountingOffice')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get();
    }

    public function approve(string $linkUuid, int $tenantId, ApproveAccessDTO $dto, ?int $actorId): AccountingOfficeTenant
    {
        return DB::transaction(function () use ($linkUuid, $tenantId, $dto, $actorId) {
            $link = $this->resolveOwnedLink($linkUuid, $tenantId);

            if ($link->status === AccountingAccessStatus::Approved->value) {
                throw new AccountingAccessException(__('messages.accounting_access.already_approved'));
            }

            $link->fill([
                'status' => AccountingAccessStatus::Approved->value,
                'scopes' => $dto->scopes,
                'approved_at' => now(),
                'revoked_at' => null,
                'approved_by' => $actorId,
            ])->save();

            event(new AccountingAccessApproved(
                linkUuid: $link->uuid,
                tenantId: $tenantId,
                scopes: $dto->scopes,
                actorId: $actorId,
            ));

            return $link->fresh(['accountingOffice']);
        });
    }

    public function revoke(string $linkUuid, int $tenantId, ?int $actorId): AccountingOfficeTenant
    {
        return DB::transaction(function () use ($linkUuid, $tenantId, $actorId) {
            $link = $this->resolveOwnedLink($linkUuid, $tenantId);

            $link->fill([
                'status' => AccountingAccessStatus::Revoked->value,
                'revoked_at' => now(),
            ])->save();

            event(new AccountingAccessRevoked(
                linkUuid: $link->uuid,
                tenantId: $tenantId,
                actorId: $actorId,
            ));

            return $link->fresh(['accountingOffice']);
        });
    }

    private function resolveOwnedLink(string $linkUuid, int $tenantId): AccountingOfficeTenant
    {
        $link = AccountingOfficeTenant::where('uuid', $linkUuid)->first();

        if (!$link || (int) $link->tenant_id !== $tenantId) {
            abort(404);
        }

        return $link;
    }
}
