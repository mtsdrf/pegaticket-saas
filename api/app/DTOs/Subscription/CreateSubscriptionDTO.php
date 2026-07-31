<?php

namespace App\DTOs\Subscription;

class CreateSubscriptionDTO
{
    public function __construct(
        public readonly string $billingPeriod,
        public readonly bool $acceptedTerms,
        public readonly ?string $planUuid = null,
        public readonly ?string $cardToken = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            billingPeriod: (string) $data['billing_period'],
            acceptedTerms: (bool) ($data['accepted_terms'] ?? false),
            planUuid: isset($data['plan_id']) ? (string) $data['plan_id'] : null,
            cardToken: isset($data['card_token']) ? (string) $data['card_token'] : null,
        );
    }
}
