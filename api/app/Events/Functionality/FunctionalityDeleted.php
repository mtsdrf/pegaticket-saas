<?php

namespace App\Events\Functionality;

class FunctionalityDeleted
{
    public function __construct(
        public string $functionalityUuid,
        public int $actorId
    )
    {
        //
    }
}