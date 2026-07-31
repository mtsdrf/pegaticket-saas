<?php

namespace App\Events\Plan;

class PlanFunctionalitiesSynced
{
    public function __construct(
        public string $planUuid,
        public int $actorId
    ) {
    }
}
