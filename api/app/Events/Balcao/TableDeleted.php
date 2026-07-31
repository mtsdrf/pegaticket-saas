<?php

namespace App\Events\Balcao;

class TableDeleted
{
    public function __construct(
        public string $tableUuid,
        public int $actorId
    ) {
    }
}
