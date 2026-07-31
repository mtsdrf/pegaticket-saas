<?php

namespace App\Events\Group;

class GroupCreated
{
    public function __construct(
        public string $groupUuid,
        public int $actorId
    )
    {
        //
    }
}