<?php

namespace App\Listeners\Payment;

use App\Events\Payment\TenantPagBankConnectionStatusChanged;
use App\Models\AuditLog;

/**
 * Dado financeiro sensível (conexão OAuth com PSP) — o meta gravado nunca
 * inclui token/código/segredo, só o par de status da transição.
 */
class AuditTenantPagBankConnectionStatusChanged
{
    public function handle(TenantPagBankConnectionStatusChanged $event): void
    {
        AuditLog::record(
            event: 'tenant_pagbank_connection_status_changed',
            model: null,
            meta: [
                'tenant_id' => $event->tenantId,
                'from_status' => $event->fromStatus,
                'to_status' => $event->toStatus,
            ],
            actorId: $event->actorId
        );
    }
}
