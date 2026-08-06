<?php

namespace App\Listeners\Report;

use App\Events\Report\CustomReportDefinitionUpdated;
use App\Models\AuditLog;

class AuditCustomReportDefinitionUpdated
{
    public function handle(CustomReportDefinitionUpdated $event): void
    {
        AuditLog::record(
            event: 'custom_report_definition_updated',
            model: null,
            meta: [
                'custom_report_definition_uuid' => $event->definitionUuid,
                'tenant_id' => $event->tenantId,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
