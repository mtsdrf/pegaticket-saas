<?php

namespace App\Listeners\Portal;

use App\Events\Portal\FinalCustomerRegistered;
use App\Events\Portal\PortalLinkConfirmed;
use App\Events\Portal\PortalOtpRequested;
use App\Events\Portal\PortalOtpVerificationFailed;
use App\Events\Portal\PortalOtpVerified;
use App\Models\AuditLog;

/**
 * Mesmo padrão de App\Listeners\WriteAuditLog (catch-all para os eventos de
 * Auth) — o bounded context "portal do cliente final" é pequeno e coeso o
 * bastante para compartilhar um listener só, em vez de 5 classes Audit*
 * dedicadas.
 *
 * `actorId` é SEMPRE null aqui, de propósito: `audit_logs.user_id` é
 * conceitualmente o id de um App\Models\User\User (staff), incluindo a
 * relação AuditLog::user(). Um FinalCustomer nunca é o "ator" nesse
 * sentido — colocar `final_customers.id` em `user_id` arriscaria colidir
 * com um `users.id` real (namespaces de PK distintos) e mostrar o nome de
 * um funcionário errado na tela de auditoria. A identidade do cliente final
 * vai sempre em `meta`, nunca em `user_id`.
 */
class WritePortalAuditLog
{
    public function handle(object $event): void
    {
        match (true) {
            $event instanceof FinalCustomerRegistered =>
            AuditLog::record('portal_customer_registered', null, [
                'final_customer_uuid' => $event->finalCustomerUuid,
                'email' => $event->email,
            ]),

            $event instanceof PortalOtpRequested =>
            AuditLog::record('portal_otp_requested', null, [
                'final_customer_uuid' => $event->finalCustomerUuid,
            ]),

            $event instanceof PortalOtpVerified =>
            AuditLog::record('portal_otp_verified', null, [
                'final_customer_uuid' => $event->finalCustomerUuid,
            ]),

            $event instanceof PortalOtpVerificationFailed =>
            AuditLog::record('portal_otp_verification_failed', null, [
                'email' => $event->email,
                'reason' => $event->reason,
            ]),

            $event instanceof PortalLinkConfirmed =>
            AuditLog::record('portal_link_confirmed', null, [
                'final_customer_uuid' => $event->finalCustomerUuid,
                'tenant_uuid' => $event->tenantUuid,
                'client_uuid' => $event->clientUuid,
            ]),

            default => null,
        };
    }
}
