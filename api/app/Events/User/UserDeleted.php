<?php

namespace App\Events\User;

class UserDeleted
{
    public function __construct(
        public readonly string $userUuid,
        public readonly int $actorId
    )
    {
        //
    }
}