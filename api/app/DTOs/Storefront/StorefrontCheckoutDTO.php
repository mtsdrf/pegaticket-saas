<?php

namespace App\DTOs\Storefront;

class StorefrontCheckoutDTO
{
    /**
     * @param array<int, array{product_uuid: string, quantity: float, notes?: string, options?: array<int, array{product_option_uuid: string, quantity?: int}>}> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $holdUuid,
        public readonly ?string $sessionToken,
        public readonly string $clientName,
        public readonly string $clientLastName,
        public readonly string $clientPhone,
        public readonly ?string $notes,
        public readonly ?string $couponCode = null,
        public readonly ?string $paymentMethod = null,
        public readonly bool $needsChange = false,
        public readonly ?float $changeForAmount = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            items: $data['items'],
            holdUuid: $data['hold_uuid'] ?? null,
            sessionToken: $data['session_token'] ?? null,
            clientName: $data['client_name'],
            clientLastName: $data['client_last_name'],
            clientPhone: $data['client_phone'],
            notes: $data['notes'] ?? null,
            couponCode: $data['coupon_code'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
            needsChange: (bool) ($data['needs_change'] ?? false),
            changeForAmount: isset($data['change_for_amount']) ? (float) $data['change_for_amount'] : null,
        );
    }
}
