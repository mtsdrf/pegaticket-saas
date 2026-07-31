<?php

namespace App\Events\Functionality;

class FunctionalityCreated
{
    public function __construct(
        public string $functionalityUuid,
        public int $actorId
    )
    {
        //
    }
}