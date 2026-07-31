<?php

namespace App\DTOs\Storefront;

class UpdateReactivationRuleDTO
{
    public function __construct(
        public readonly int $daysWithoutOrder,
        public readonly string $couponType,
        public readonly float $couponValue,
        public readonly int $couponValidityDays,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            daysWithoutOrder: (int) $data['days_without_order'],
            couponType: $data['coupon_type'],
            couponValue: (float) $data['coupon_value'],
            couponValidityDays: (int) $data['coupon_validity_days'],
            isActive: (bool) ($data['is_active'] ?? false),
        );
    }
}
