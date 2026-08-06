<?php

namespace App\DTOs\Report;

class CreateCustomReportDefinitionDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $dataSource,
        public readonly array $dimensions,
        public readonly array $metrics,
        public readonly array $calculatedMetrics,
        public readonly array $filters
    ) {}

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            dataSource: $data['data_source'],
            dimensions: $data['dimensions'] ?? [],
            metrics: $data['metrics'] ?? [],
            calculatedMetrics: $data['calculated_metrics'] ?? [],
            filters: $data['filters'] ?? [],
        );
    }
}
