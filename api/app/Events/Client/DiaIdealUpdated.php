<?php

namespace App\Events\Client;

class DiaIdealUpdated
{
    public function __construct(
        public string $diaIdealUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
