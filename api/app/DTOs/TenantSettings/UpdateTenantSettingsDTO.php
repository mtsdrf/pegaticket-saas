<?php

namespace App\DTOs\TenantSettings;

class UpdateTenantSettingsDTO
{
    public function __construct(
        public readonly ?array $acceptedPaymentMethods = null,
        public readonly string $paymentReceivingMethod = 'manual',
        public readonly ?string $paymentPixKey = null,
        public readonly string $pagBankIntegrationMode = 'disabled',
        public readonly string $pagBankEnvironment = 'sandbox',
        public readonly bool $hasPagBankAccessTokenInput = false,
        public readonly ?string $pagBankAccessToken = null,
        public readonly ?string $pagBankReceiverAccountId = null,
        public readonly bool $storefrontEnabled = true,
        public readonly string $catalogLayout = 'list',
        public readonly ?int $holdDurationMinutes = null,
        public readonly ?float $affiliateDefaultCommissionPercentage = null,
        public readonly ?string $metaPixelId = null,
        public readonly ?string $googleAnalyticsId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            acceptedPaymentMethods: isset($data['accepted_payment_methods'])
                ? array_values($data['accepted_payment_methods'])
                : null,
            paymentReceivingMethod: isset($data['payment_receiving_method'])
                ? (string) $data['payment_receiving_method']
                : 'manual',
            paymentPixKey: isset($data['payment_pix_key']) && $data['payment_pix_key'] !== ''
                ? (string) $data['payment_pix_key']
                : null,
            pagBankIntegrationMode: isset($data['pagbank_integration_mode'])
                ? (string) $data['pagbank_integration_mode']
                : 'disabled',
            pagBankEnvironment: isset($data['pagbank_environment'])
                ? (string) $data['pagbank_environment']
                : 'sandbox',
            hasPagBankAccessTokenInput: array_key_exists('pagbank_access_token', $data),
            pagBankAccessToken: array_key_exists('pagbank_access_token', $data)
                ? (($data['pagbank_access_token'] ?? '') !== '' ? (string) $data['pagbank_access_token'] : null)
                : null,
            pagBankReceiverAccountId: isset($data['pagbank_receiver_account_id']) && $data['pagbank_receiver_account_id'] !== ''
                ? (string) $data['pagbank_receiver_account_id']
                : null,
            storefrontEnabled: (bool) ($data['storefront_enabled'] ?? true),
            catalogLayout: isset($data['catalog_layout']) ? (string) $data['catalog_layout'] : 'list',
            holdDurationMinutes: isset($data['hold_duration_minutes']) ? (int) $data['hold_duration_minutes'] : null,
            affiliateDefaultCommissionPercentage: isset($data['affiliate_default_commission_percentage'])
                ? (float) $data['affiliate_default_commission_percentage']
                : null,
            metaPixelId: isset($data['meta_pixel_id']) && $data['meta_pixel_id'] !== ''
                ? (string) $data['meta_pixel_id']
                : null,
            googleAnalyticsId: isset($data['google_analytics_id']) && $data['google_analytics_id'] !== ''
                ? (string) $data['google_analytics_id']
                : null,
        );
    }
}
