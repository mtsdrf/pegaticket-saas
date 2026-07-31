<?php

namespace App\Listeners\Accounting;

use App\Models\AuditLog;

/**
 * Listener genérico de auditoria do módulo do contador (roadmap 2C), mesmo
 * padrão de App\Listeners\Audit\AuditGroupListener: grava o nome do evento e
 * suas props públicas em audit_logs. O ator do contador NÃO é um User — quando
 * `actorId` é null (fluxos do contador), user_id fica null e a identidade do
 * escritório vai nas props do evento (meta), ver AuditLog::record.
 */
class AuditAccountingListener
{
    public function handle(object $event): void
    {
        $actorId = $event->actorId ?? null;

        // Eventos do lado do TENANT (approve/revoke/mensagem do tenant) trazem
        // um actorId real de `users`. Eventos do lado do CONTADOR não têm ator
        // User — usar recordForNonUser força user_id=null (senão o fallback
        // safeUserId() resolveria o subject do JWT do contador e gravaria um id
        // que não é de `users`). Ver AuditLog::recordForNonUser.
        if ($actorId !== null) {
            AuditLog::record(
                event: class_basename($event),
                model: null,
                meta: get_object_vars($event),
                actorId: $actorId
            );

            return;
        }

        AuditLog::recordForNonUser(class_basename($event), get_object_vars($event));
    }
}
