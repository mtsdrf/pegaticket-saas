<?php

namespace App\Events\User;

class UserCreated
{
    public function __construct(
        public readonly string $userUuid,
        public readonly int $actorId
    )
    {
        //
    }
}
