<?php

namespace App\Events\Subscription;

class SubscriptionWithdrawalRequested
{
    public function __construct(
        public string $subscriptionUuid,
        public string $refundProtocol,
        public ?int $actorId
    ) {
    }
}
