<?php

namespace App\Events\Balcao;

class TableUpdated
{
    /**
     * @param array<int, string> $changes
     */
    public function __construct(
        public string $tableUuid,
        public int $actorId,
        public array $changes = []
    ) {
    }
}
