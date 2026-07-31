<?php

namespace App\Events\User;

class UserProfileUpdated
{
    public function __construct(
        public readonly string $userUuid,
        public readonly int $actorId,
        public readonly array $changes
    )
    {
        //
    }
}
