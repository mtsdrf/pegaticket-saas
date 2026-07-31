<?php

namespace App\DTOs\Subscription;

class ChangeSubscriptionPlanDTO
{
    public function __construct(
        public readonly string $planUuid,
        public readonly string $billingPeriod,
        public readonly ?string $cardToken = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            planUuid: (string) $data['plan_id'],
            billingPeriod: (string) $data['billing_period'],
            cardToken: isset($data['card_token']) ? (string) $data['card_token'] : null,
        );
    }
}
