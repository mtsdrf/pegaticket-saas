<?php

namespace App\Listeners\Payment;

use App\Events\Payment\TenantPagBankConnectionStatusChanged;
use App\Mail\PagBankReceivingStatusChangedMail;
use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Services\Communication\CommunicationDispatcherService;
use App\Services\Logging\ApplicationLogger;
use App\Services\Payment\PagBankTransactionLogger;

/**
 * Notificação de mudança de status de recebimentos (roadmap R2.5, seção
 * 9.5.6) — segundo listener do mesmo evento já usado para auditoria
 * (AuditTenantPagBankConnectionStatusChanged), sem alterar o listener de
 * auditoria existente. O evento já só dispara quando `fromStatus !==
 * toStatus` (ver dispatchStatusChanged em PagBankConnectService/
 * PagBankAccountService) — nenhuma checagem extra de duplicidade é
 * necessária aqui para não notificar repetidamente em sync sem mudança
 * real.
 *
 * Só notifica em duas transições: para o grupo "habilitado"
 * (enabled/verified) e para o grupo "pendência" (restricted/rejected) —
 * qualquer outro status (pending_kyc, under_review, submitted, etc.) não
 * gera e-mail, por não ser uma decisão acionável pelo tenant ainda.
 *
 * Falha de envio de e-mail é logada e NUNCA propagada: este evento é
 * disparado de dentro do `DB::transaction()` que persiste a própria
 * mudança de status (ver PagBankConnectService/PagBankAccountService) —
 * deixar uma exceção de rede/SMTP escapar daqui reverteria a mudança de
 * status já confirmada no PagBank, que é um estado muito pior do que só
 * não enviar o e-mail desta vez.
 */
class NotifyTenantPagBankConnectionStatusChanged
{
    private const ENABLED_STATUSES = ['enabled', 'verified'];

    private const PENDING_STATUSES = ['restricted', 'rejected'];

    public function __construct(
        private TenantUserRepositoryInterface $tenantUserRepository,
        private CommunicationDispatcherService $communicationDispatcher,
        private PagBankTransactionLogger $metrics,
    ) {}

    public function handle(TenantPagBankConnectionStatusChanged $event): void
    {
        $variant = $this->resolveVariant($event->toStatus);

        if ($variant === null) {
            return;
        }

        $this->metrics->metric(
            $variant === PagBankReceivingStatusChangedMail::VARIANT_ENABLED
                ? 'tenant_receiving_enabled_total'
                : 'tenant_receiving_restricted_total',
            ['tenant_id' => $event->tenantId]
        );

        try {
            $tenant = Tenant::find($event->tenantId);
            $owner = $this->tenantUserRepository->findOwnerUserForTenant($event->tenantId);

            if (! $tenant || ! $owner || ! $owner->email) {
                return;
            }

            $mailable = new PagBankReceivingStatusChangedMail($variant, $event->tenantId, (string) $tenant->name);

            $this->communicationDispatcher->send(
                'pagbank_receiving_status_changed',
                $mailable,
                $owner->email,
                $event->tenantId
            );
        } catch (\Throwable $e) {
            ApplicationLogger::error('Falha ao notificar mudança de status de recebimentos PagBank', [
                'tenant_id' => $event->tenantId,
                'from_status' => $event->fromStatus,
                'to_status' => $event->toStatus,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveVariant(string $toStatus): ?string
    {
        if (in_array($toStatus, self::ENABLED_STATUSES, true)) {
            return PagBankReceivingStatusChangedMail::VARIANT_ENABLED;
        }

        if (in_array($toStatus, self::PENDING_STATUSES, true)) {
            return PagBankReceivingStatusChangedMail::VARIANT_PENDING;
        }

        return null;
    }
}
