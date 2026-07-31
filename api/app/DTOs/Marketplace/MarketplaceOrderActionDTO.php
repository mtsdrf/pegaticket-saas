<?php

namespace App\DTOs\Marketplace;

class MarketplaceOrderActionDTO
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $action,
        public readonly array $payload = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $payload = $data;
        unset($payload['action']);

        return new self(
            action: (string) $data['action'],
            payload: $payload,
        );
    }
}
