<?php

namespace App\DTOs\Subscription;

class UpdateSubscriptionPaymentMethodDTO
{
    public function __construct(
        public readonly string $cardToken,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cardToken: (string) $data['card_token'],
        );
    }
}
