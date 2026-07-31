<?php

namespace App\Events\Balcao;

class ComandaOpened
{
    public function __construct(
        public string $comandaUuid,
        public ?string $tableUuid,
        public int $actorId
    ) {
    }
}
