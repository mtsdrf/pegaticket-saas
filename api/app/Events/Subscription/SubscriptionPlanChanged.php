<?php

namespace App\Events\Subscription;

class SubscriptionPlanChanged
{
    public function __construct(
        public string $subscriptionUuid,
        public ?int $actorId
    ) {
    }
}
