<?php

namespace App\DTOs\Fiscal;

class CreateTaxRuleDTO
{
    public function __construct(
        public readonly string $taxType,
        public readonly ?array $scope,
        public readonly float $ratePercent,
        public readonly ?string $validFrom,
        public readonly ?string $validTo,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            taxType: $data['tax_type'],
            scope: $data['scope'] ?? null,
            ratePercent: (float) $data['rate_percent'],
            validFrom: $data['valid_from'] ?? null,
            validTo: $data['valid_to'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
