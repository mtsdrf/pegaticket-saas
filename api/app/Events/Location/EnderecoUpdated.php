<?php

namespace App\Events\Location;

class EnderecoUpdated
{
    public function __construct(
        public string $enderecoUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
