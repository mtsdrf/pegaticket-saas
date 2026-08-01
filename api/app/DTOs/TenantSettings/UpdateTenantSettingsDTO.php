<?php

namespace App\DTOs\TenantSettings;

class UpdateTenantSettingsDTO
{
    public function __construct(
        public readonly bool $sendTrackingLinkWhatsapp,
        public readonly ?float $minimumOrderValue = null,
        public readonly ?int $estimatedPreparationMinutes = null,
        public readonly ?array $acceptedPaymentMethods = null,
        public readonly string $paymentReceivingMethod = 'manual',
        public readonly ?string $paymentPixKey = null,
        public readonly bool $storefrontEnabled = true,
        public readonly string $catalogLayout = 'list',
        public readonly ?int $holdDurationMinutes = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sendTrackingLinkWhatsapp: (bool) $data['send_tracking_link_whatsapp'],
            minimumOrderValue: isset($data['minimum_order_value']) ? (float) $data['minimum_order_value'] : null,
            estimatedPreparationMinutes: isset($data['estimated_preparation_minutes'])
                ? (int) $data['estimated_preparation_minutes']
                : null,
            acceptedPaymentMethods: isset($data['accepted_payment_methods'])
                ? array_values($data['accepted_payment_methods'])
                : null,
            paymentReceivingMethod: isset($data['payment_receiving_method'])
                ? (string) $data['payment_receiving_method']
                : 'manual',
            paymentPixKey: isset($data['payment_pix_key']) && $data['payment_pix_key'] !== ''
                ? (string) $data['payment_pix_key']
                : null,
            storefrontEnabled: (bool) ($data['storefront_enabled'] ?? true),
            catalogLayout: isset($data['catalog_layout']) ? (string) $data['catalog_layout'] : 'list',
            holdDurationMinutes: isset($data['hold_duration_minutes']) ? (int) $data['hold_duration_minutes'] : null,
        );
    }
}
