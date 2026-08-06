<?php

namespace App\Events\Report;

class CustomReportDefinitionUpdated
{
    public function __construct(
        public string $definitionUuid,
        public int $tenantId,
        public int $actorId,
        public array $changes = []
    ) {}
}
