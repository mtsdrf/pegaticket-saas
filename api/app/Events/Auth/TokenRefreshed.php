<?php

namespace App\Events\Auth;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenRefreshed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $userUuid,
        public readonly ?int $actorId
    )
    {
        //
    }
}