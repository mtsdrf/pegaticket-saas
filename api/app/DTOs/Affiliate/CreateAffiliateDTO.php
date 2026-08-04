<?php

namespace App\DTOs\Affiliate;

class CreateAffiliateDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $email,
        public readonly string $trackingCode,
        public readonly ?float $commissionPercentage,
        public readonly string $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            trackingCode: $data['tracking_code'],
            commissionPercentage: isset($data['commission_percentage']) ? (float) $data['commission_percentage'] : null,
            status: $data['status'] ?? 'active',
        );
    }
}
