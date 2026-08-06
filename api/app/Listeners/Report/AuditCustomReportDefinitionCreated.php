<?php

namespace App\Listeners\Report;

use App\Events\Report\CustomReportDefinitionCreated;
use App\Models\AuditLog;

class AuditCustomReportDefinitionCreated
{
    public function handle(CustomReportDefinitionCreated $event): void
    {
        AuditLog::record(
            event: 'custom_report_definition_created',
            model: null,
            meta: [
                'custom_report_definition_uuid' => $event->definitionUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
