<?php

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateAccountingMessageDTO;
use App\Enums\Accounting\AccountingAccessStatus;
use App\Events\Accounting\AccountingMessageSent;
use App\Exceptions\AccountingAccessException;
use App\Models\Accounting\AccountingOfficeTenant;
use App\Models\Accounting\AccountingRequestMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Central de pendências tenant <-> contador (roadmap 2C). Mensagens são
 * escopadas por um vínculo APROVADO (accounting_office_tenant). `sender_type`
 * distingue quem enviou. Anexo opcional via Storage::disk('public') (mesmo
 * mecanismo de imagem de produto). Ao enviar, marca como `answered` as
 * mensagens `open` do outro lado (fluxo de status simples).
 */
class AccountingMessageService
{
    public const SENDER_TENANT = 'tenant';
    public const SENDER_OFFICE = 'accounting_office';

    public function listForLink(AccountingOfficeTenant $link): Collection
    {
        return AccountingRequestMessage::where('accounting_office_tenant_id', $link->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve um vínculo aprovado pertencente ao tenant (lado do tenant).
     */
    public function resolveApprovedTenantLink(string $linkUuid, int $tenantId): AccountingOfficeTenant
    {
        $link = AccountingOfficeTenant::where('uuid', $linkUuid)->first();

        if (!$link || (int) $link->tenant_id !== $tenantId) {
            abort(404);
        }

        if ($link->status !== AccountingAccessStatus::Approved->value) {
            throw new AccountingAccessException(__('messages.accounting_access.not_approved'));
        }

        return $link;
    }

    public function create(
        AccountingOfficeTenant $link,
        CreateAccountingMessageDTO $dto,
        string $senderType,
        ?int $senderUserId,
        ?UploadedFile $attachment
    ): AccountingRequestMessage {
        return DB::transaction(function () use ($link, $dto, $senderType, $senderUserId, $attachment) {
            $attachmentPath = null;
            $attachmentName = null;

            if ($attachment) {
                $attachmentPath = $attachment->store('accounting-messages', 'public');
                $attachmentName = $attachment->getClientOriginalName();
            }

            // Marca como respondidas as pendências abertas do OUTRO lado.
            AccountingRequestMessage::where('accounting_office_tenant_id', $link->id)
                ->where('sender_type', '!=', $senderType)
                ->where('status', 'open')
                ->update(['status' => 'answered']);

            $message = AccountingRequestMessage::create([
                'accounting_office_tenant_id' => $link->id,
                'tenant_id' => $link->tenant_id,
                'sender_type' => $senderType,
                'sender_user_id' => $senderUserId,
                'body' => $dto->body,
                'due_date' => $dto->dueDate,
                'status' => 'open',
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
            ]);

            event(new AccountingMessageSent(
                messageUuid: $message->uuid,
                linkUuid: $link->uuid,
                senderType: $senderType,
                actorId: $senderUserId,
            ));

            return $message;
        });
    }
}
