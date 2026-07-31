<?php

namespace App\Events\Balcao;

class ComandaItemCancelled
{
    public function __construct(
        public int $itemId,
        public string $comandaUuid,
        public string $itemUuid,
        public string $fromStatus,
        public ?string $reason,
        public int $actorId
    ) {
    }
}
