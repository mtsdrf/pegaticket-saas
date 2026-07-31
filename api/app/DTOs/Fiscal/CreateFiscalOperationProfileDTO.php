<?php

namespace App\DTOs\Fiscal;

class CreateFiscalOperationProfileDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $operationNature,
        public readonly string $documentType,
        public readonly ?string $defaultCfop,
        public readonly ?array $scope,
        public readonly ?string $description,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            operationNature: $data['operation_nature'],
            documentType: $data['document_type'],
            defaultCfop: isset($data['default_cfop']) && $data['default_cfop'] !== '' ? trim($data['default_cfop']) : null,
            scope: self::normalizeScope($data['scope'] ?? null),
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim($data['description']) : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    protected static function normalizeScope(?array $scope): ?array
    {
        if (!$scope) {
            return null;
        }

        $normalized = [];

        foreach (['order_origin', 'fulfillment_type', 'destination_type'] as $key) {
            if (!empty($scope[$key]) && is_array($scope[$key])) {
                $values = array_values(array_unique(array_filter(array_map(
                    fn ($value) => trim((string) $value),
                    $scope[$key]
                ))));

                if ($values !== []) {
                    $normalized[$key] = $values;
                }
            }
        }

        return $normalized !== [] ? $normalized : null;
    }
}
