<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\ReactivationDispatched;
use App\Models\AuditLog;

/**
 * Ator do sistema (comando agendado, sem usuário autenticado) —
 * AuditLog::record() aceita actorId nulo (grava user_id=null), mesmo
 * padrão já usado por eventos sem "ator humano" no projeto.
 */
class AuditReactivationDispatched
{
    public function handle(ReactivationDispatched $event): void
    {
        AuditLog::record(
            event: 'reactivation_dispatched',
            model: null,
            meta: [
                'tenant_id' => $event->tenantId,
                'client_id' => $event->clientId,
                'coupon_code' => $event->couponCode,
            ],
            actorId: null
        );
    }
}
