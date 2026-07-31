<?php

namespace App\Events\Location;

class EnderecoCreated
{
    public function __construct(
        public string $enderecoUuid,
        public ?int $actorId
    ) {
    }
}
