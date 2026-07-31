<?php

namespace App\DTOs\Group;

class SyncGroupPermissionsDTO
{
    public function __construct(public readonly array $permissions)
    {
        //
    }

    public static function fromArray(array $data): self
    {
        return new self(
            permissions: $data['permissions']
        );
    }
}
