<?php

namespace App\DTOs\Marketplace;

class UpsertMarketplaceIntegrationDTO
{
    public function __construct(
        public readonly string $provider,
        public readonly string $name,
        public readonly string $environment,
        public readonly bool $isActive,
        public readonly ?string $clientId,
        public readonly ?string $clientSecret,
        public readonly ?string $authorizationCode,
        public readonly ?string $merchantId,
        public readonly ?string $webhookUrl,
        public readonly ?string $pollingMerchantIds,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) $data['provider'],
            name: (string) $data['name'],
            environment: (string) $data['environment'],
            isActive: (bool) ($data['is_active'] ?? true),
            clientId: isset($data['client_id']) ? (string) $data['client_id'] : null,
            clientSecret: isset($data['client_secret']) ? (string) $data['client_secret'] : null,
            authorizationCode: isset($data['authorization_code']) ? (string) $data['authorization_code'] : null,
            merchantId: isset($data['merchant_id']) ? (string) $data['merchant_id'] : null,
            webhookUrl: isset($data['webhook_url']) ? (string) $data['webhook_url'] : null,
            pollingMerchantIds: isset($data['polling_merchant_ids']) ? (string) $data['polling_merchant_ids'] : null,
        );
    }
}
