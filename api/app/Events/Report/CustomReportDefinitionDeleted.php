<?php

namespace App\Events\Report;

class CustomReportDefinitionDeleted
{
    public function __construct(
        public string $definitionUuid,
        public int $tenantId,
        public int $actorId
    ) {}
}
