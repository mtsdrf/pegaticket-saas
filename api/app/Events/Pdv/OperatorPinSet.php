<?php

namespace App\Events\Pdv;

class OperatorPinSet
{
    public function __construct(
        public string $tenantUuid,
        public string $userUuid,
        public int $actorId
    ) {
    }
}
