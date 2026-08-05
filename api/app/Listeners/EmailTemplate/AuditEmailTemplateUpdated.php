<?php

namespace App\Listeners\EmailTemplate;

use App\Events\EmailTemplate\EmailTemplateUpdated;
use App\Models\AuditLog;

class AuditEmailTemplateUpdated
{
    public function handle(EmailTemplateUpdated $event): void
    {
        AuditLog::record(
            event: 'email_template_updated',
            model: null,
            meta: [
                'email_template_uuid' => $event->emailTemplateUuid,
                'type' => $event->type,
            ],
            actorId: $event->actorId
        );
    }
}
