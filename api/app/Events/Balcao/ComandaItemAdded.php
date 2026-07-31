<?php

namespace App\Events\Balcao;

class ComandaItemAdded
{
    public function __construct(
        public string $comandaUuid,
        public string $itemUuid,
        public int $actorId
    ) {
    }
}
