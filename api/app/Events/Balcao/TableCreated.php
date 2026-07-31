<?php

namespace App\Events\Balcao;

class TableCreated
{
    public function __construct(
        public string $tableUuid,
        public int $actorId
    ) {
    }
}
