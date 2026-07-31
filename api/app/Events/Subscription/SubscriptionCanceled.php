<?php

namespace App\Events\Subscription;

class SubscriptionCanceled
{
    public function __construct(
        public string $subscriptionUuid,
        public bool $immediately,
        public ?int $actorId
    ) {
    }
}
