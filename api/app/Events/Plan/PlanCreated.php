<?php

namespace App\Events\Plan;

class PlanCreated
{
    public function __construct(
        public string $planUuid,
        public int $actorId
    ) {
    }
}
