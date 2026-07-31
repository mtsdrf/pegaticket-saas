<?php

namespace App\Events\Balcao;

class ComandaItemPrepStatusUpdated
{
    public function __construct(
        public int $itemId,
        public string $comandaUuid,
        public string $itemUuid,
        public string $fromStatus,
        public string $toStatus,
        public int $actorId
    ) {
    }
}
