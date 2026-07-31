<?php

namespace App\Events\Pdv;

class OperatorSessionResolved
{
    public function __construct(
        public string $tenantUuid,
        public string $operatorUuid,
        public int $actorId
    ) {
    }
}
