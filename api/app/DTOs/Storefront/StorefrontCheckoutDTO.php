<?php

namespace App\DTOs\Storefront;

class StorefrontCheckoutDTO
{
    /**
     * @param  array<int, array{ticket_type_uuid?: string, event_product_uuid?: string, quantity: float, notes?: string, participants?: array<int, array{name?: string, document?: string}>}>  $items
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
        // Fallback de atribuição de afiliado quando não há hold (o caminho
        // principal é o hold já carregar affiliate_id, capturado na
        // criação — ver StorefrontHoldService::createHold()).
        public readonly ?string $affiliateCode = null,
        public readonly ?string $paymentMethod = null,
        public readonly bool $needsChange = false,
        public readonly ?float $changeForAmount = null,
        // UTM de campanha (Fase 6, fatia 2) — capturado pelo frontend a
        // partir de ?utm_source=/&utm_medium=/&utm_campaign= na URL da loja
        // e persistido em localStorage (mesmo espírito de affiliateCode).
        public readonly ?string $utmSource = null,
        public readonly ?string $utmMedium = null,
        public readonly ?string $utmCampaign = null,
    ) {}

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
            affiliateCode: $data['affiliate_code'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
            needsChange: (bool) ($data['needs_change'] ?? false),
            changeForAmount: isset($data['change_for_amount']) ? (float) $data['change_for_amount'] : null,
            utmSource: $data['utm_source'] ?? null,
            utmMedium: $data['utm_medium'] ?? null,
            utmCampaign: $data['utm_campaign'] ?? null,
        );
    }
}
