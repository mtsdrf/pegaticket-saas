<?php

namespace App\Events\Group;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupUsersSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $groupUuid,
        public readonly array $userUuids,
        public readonly ?int $actorId
    )
    {
        //
    }
}
