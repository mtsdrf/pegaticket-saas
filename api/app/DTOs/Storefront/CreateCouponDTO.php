<?php

namespace App\DTOs\Storefront;

class CreateCouponDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $type,
        public readonly ?float $value,
        public readonly ?float $minimumOrderValue,
        public readonly ?int $maxUsesTotal,
        public readonly ?int $maxUsesPerCustomer,
        public readonly ?string $startsAt,
        public readonly ?string $expiresAt,
        public readonly bool $isActive,
        public readonly ?array $allowedPaymentMethods = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            type: $data['type'],
            value: isset($data['value']) ? (float) $data['value'] : null,
            minimumOrderValue: isset($data['minimum_order_value']) ? (float) $data['minimum_order_value'] : null,
            maxUsesTotal: isset($data['max_uses_total']) ? (int) $data['max_uses_total'] : null,
            maxUsesPerCustomer: isset($data['max_uses_per_customer']) ? (int) $data['max_uses_per_customer'] : null,
            startsAt: $data['starts_at'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            allowedPaymentMethods: isset($data['allowed_payment_methods'])
                ? array_values($data['allowed_payment_methods'])
                : null,
        );
    }
}
