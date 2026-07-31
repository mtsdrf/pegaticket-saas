<?php

namespace App\Events\Balcao;

class ComandaItemSentToStation
{
    public function __construct(
        public int $itemId,
        public string $comandaUuid,
        public string $itemUuid,
        public ?string $stationUuid,
        public int $actorId
    ) {
    }
}
