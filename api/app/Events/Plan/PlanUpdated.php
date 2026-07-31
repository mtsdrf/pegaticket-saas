<?php

namespace App\Events\Plan;

class PlanUpdated
{
    public function __construct(
        public string $planUuid,
        public int $actorId,
        public array $changes = []
    ) {
    }
}
