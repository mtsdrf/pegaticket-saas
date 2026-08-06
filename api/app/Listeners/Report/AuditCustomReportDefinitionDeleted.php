<?php

namespace App\Listeners\Report;

use App\Events\Report\CustomReportDefinitionDeleted;
use App\Models\AuditLog;

class AuditCustomReportDefinitionDeleted
{
    public function handle(CustomReportDefinitionDeleted $event): void
    {
        AuditLog::record(
            event: 'custom_report_definition_deleted',
            model: null,
            meta: [
                'custom_report_definition_uuid' => $event->definitionUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
