<?php

namespace App\DTOs\Webhook;

class UpdateWebhookSubscriptionDTO
{
    /**
     * @param array<int, string> $eventTypes
     */
    public function __construct(
        public readonly string $url,
        public readonly array $eventTypes,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            eventTypes: $data['event_types'],
            isActive: array_key_exists('is_active', $data) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
        );
    }
}
