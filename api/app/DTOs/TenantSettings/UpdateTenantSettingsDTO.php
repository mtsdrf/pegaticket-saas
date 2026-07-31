<?php

namespace App\DTOs\TenantSettings;

class UpdateTenantSettingsDTO
{
    public function __construct(
        public readonly bool $sendTrackingLinkWhatsapp,
        public readonly bool $blockOrderWithoutStock,
        public readonly ?float $minimumOrderValue = null,
        public readonly ?int $estimatedPreparationMinutes = null,
        public readonly bool $cashbackEnabled = false,
        public readonly ?float $cashbackPercentage = null,
        public readonly ?float $cashbackMaxPerOrder = null,
        public readonly ?int $cashbackHoldDays = null,
        public readonly ?int $cashbackExpirationDays = null,
        public readonly ?float $cashbackRedeemMaxPercentage = null,
        public readonly ?string $cashbackName = null,
        public readonly ?array $acceptedPaymentMethods = null,
        public readonly string $paymentReceivingMethod = 'manual',
        public readonly ?string $paymentPixKey = null,
        public readonly bool $allowStorePickup = false,
        public readonly bool $allowDelivery = true,
        public readonly bool $storefrontEnabled = true,
        public readonly string $catalogLayout = 'list',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sendTrackingLinkWhatsapp: (bool) $data['send_tracking_link_whatsapp'],
            blockOrderWithoutStock: (bool) $data['block_order_without_stock'],
            minimumOrderValue: isset($data['minimum_order_value']) ? (float) $data['minimum_order_value'] : null,
            estimatedPreparationMinutes: isset($data['estimated_preparation_minutes'])
                ? (int) $data['estimated_preparation_minutes']
                : null,
            cashbackEnabled: (bool) ($data['cashback_enabled'] ?? false),
            cashbackPercentage: isset($data['cashback_percentage']) ? (float) $data['cashback_percentage'] : null,
            cashbackMaxPerOrder: isset($data['cashback_max_per_order']) ? (float) $data['cashback_max_per_order'] : null,
            cashbackHoldDays: isset($data['cashback_hold_days']) ? (int) $data['cashback_hold_days'] : null,
            cashbackExpirationDays: isset($data['cashback_expiration_days'])
                ? (int) $data['cashback_expiration_days']
                : null,
            cashbackRedeemMaxPercentage: isset($data['cashback_redeem_max_percentage'])
                ? (float) $data['cashback_redeem_max_percentage']
                : null,
            cashbackName: isset($data['cashback_name']) ? (string) $data['cashback_name'] : null,
            acceptedPaymentMethods: isset($data['accepted_payment_methods'])
                ? array_values($data['accepted_payment_methods'])
                : null,
            paymentReceivingMethod: isset($data['payment_receiving_method'])
                ? (string) $data['payment_receiving_method']
                : 'manual',
            paymentPixKey: isset($data['payment_pix_key']) && $data['payment_pix_key'] !== ''
                ? (string) $data['payment_pix_key']
                : null,
            allowStorePickup: (bool) ($data['allow_store_pickup'] ?? false),
            allowDelivery: (bool) ($data['allow_delivery'] ?? true),
            storefrontEnabled: (bool) ($data['storefront_enabled'] ?? true),
            catalogLayout: isset($data['catalog_layout']) ? (string) $data['catalog_layout'] : 'list',
        );
    }
}
