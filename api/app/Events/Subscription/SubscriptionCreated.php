<?php

namespace App\Events\Subscription;

class SubscriptionCreated
{
    public function __construct(
        public string $subscriptionUuid,
        public ?int $actorId
    ) {
    }
}
