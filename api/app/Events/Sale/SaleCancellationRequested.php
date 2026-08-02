<?php

namespace App\Events\Sale;

/**
 * Disparado pelo cliente final via Portal (nunca pelo staff) —
 * actorId sempre null aqui, mesmo padrão de
 * App\Listeners\Portal\WritePortalAuditLog: audit_logs.user_id é
 * conceitualmente um App\Models\User\User (staff), FinalCustomer nunca é
 * o "ator" nesse sentido. Identidade do cliente final vai em
 * finalCustomerUuid (meta do audit log), nunca em user_id.
 */
class SaleCancellationRequested
{
    public function __construct(
        public string $saleUuid,
        public ?string $reason,
        public ?string $finalCustomerUuid,
    ) {
    }
}
