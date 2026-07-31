<?php

namespace App\Events\Group;

class GroupDeleted
{
    public function __construct(
        public string $groupUuid,
        public int $actorId
    )
    {
        //
    }
}