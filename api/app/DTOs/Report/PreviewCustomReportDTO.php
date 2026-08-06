<?php

namespace App\DTOs\Report;

/**
 * Definição ad-hoc (não salva) usada só pelo endpoint de pré-visualização
 * (`POST /custom-report-definitions/preview`) — mesma validação/execução
 * de uma definição salva, via App\Services\Report\CustomReportQueryBuilder,
 * mas sem persistir nada.
 */
class PreviewCustomReportDTO
{
    public function __construct(
        public readonly string $dataSource,
        public readonly array $dimensions,
        public readonly array $metrics,
        public readonly array $calculatedMetrics,
        public readonly array $filters,
        public readonly int $page = 1,
        public readonly int $perPage = 20
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            dataSource: $data['data_source'],
            dimensions: $data['dimensions'] ?? [],
            metrics: $data['metrics'] ?? [],
            calculatedMetrics: $data['calculated_metrics'] ?? [],
            filters: $data['filters'] ?? [],
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? 20),
        );
    }
}
