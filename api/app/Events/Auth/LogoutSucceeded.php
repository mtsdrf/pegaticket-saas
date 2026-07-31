<?php

namespace App\Events\Auth;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LogoutSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?int $actorId
    )
    {
        //
    }
}