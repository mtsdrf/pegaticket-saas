<?php

namespace App\Listeners\EmailTemplate;

use App\Events\EmailTemplate\EmailTemplateReset;
use App\Models\AuditLog;

class AuditEmailTemplateReset
{
    public function handle(EmailTemplateReset $event): void
    {
        AuditLog::record(
            event: 'email_template_reset',
            model: null,
            meta: [
                'tenant_id' => $event->tenantId,
                'type' => $event->type,
            ],
            actorId: $event->actorId
        );
    }
}
