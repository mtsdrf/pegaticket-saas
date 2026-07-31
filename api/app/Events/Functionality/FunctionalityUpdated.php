<?php

namespace App\Events\Functionality;

class FunctionalityUpdated
{
    public function __construct(
        public string $functionalityUuid,
        public int $actorId,
        public readonly array $changes
    )
    {
        //
    }
}