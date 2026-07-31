<?php

namespace App\DTOs\Subscription;

class CancelSubscriptionDTO
{
    public function __construct(
        public readonly bool $immediately,
        public readonly ?string $reason,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            immediately: (bool) ($data['immediately'] ?? false),
            reason: $data['reason'] ?? null,
        );
    }
}
