<?php

namespace App\Events\User;

class UserPasswordChanged
{
    public function __construct(
        public readonly string $userUuid,
        public readonly int $actorId
    )
    {
        //
    }
}
