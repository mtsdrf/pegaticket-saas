<?php

namespace App\Events\Group;

class GroupUpdated
{
    public function __construct(
        public string $groupUuid,
        public int $actorId,
        public readonly array $changes
    )
    {
        //
    }
}