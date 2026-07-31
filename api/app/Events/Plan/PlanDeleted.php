<?php

namespace App\Events\Plan;

class PlanDeleted
{
    public function __construct(
        public string $planUuid,
        public int $actorId
    ) {
    }
}
