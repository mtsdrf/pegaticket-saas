<?php

namespace App\Events\Location;

class EnderecoDeleted
{
    public function __construct(
        public string $enderecoUuid,
        public int $actorId
    ) {
    }
}
