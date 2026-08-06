<?php

namespace App\DTOs\Report;

class UpdateCustomReportDefinitionDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $dataSource = null,
        public readonly ?array $dimensions = null,
        public readonly ?array $metrics = null,
        public readonly ?array $calculatedMetrics = null,
        public readonly ?array $filters = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            dataSource: $data['data_source'] ?? null,
            dimensions: $data['dimensions'] ?? null,
            metrics: $data['metrics'] ?? null,
            calculatedMetrics: $data['calculated_metrics'] ?? null,
            filters: $data['filters'] ?? null,
        );
    }
}
