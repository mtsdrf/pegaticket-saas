<?php

namespace App\DTOs\Group;

class SyncGroupUsersDTO
{
    public function __construct(public readonly array $userUuids)
    {
        //
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userUuids: $data['user_uuids']
        );
    }
}