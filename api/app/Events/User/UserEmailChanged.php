<?php

namespace App\Events\User;

class UserEmailChanged
{
    public function __construct(
        public readonly string $userUuid,
        public readonly int $actorId,
        public readonly string $oldEmail,
        public readonly string $newEmail
    )
    {
        //
    }
}
