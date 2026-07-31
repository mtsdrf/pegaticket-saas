<?php

namespace App\Events\Accounting;

use Illuminate\Foundation\Events\Dispatchable;

class AccountingLoginFailed
{
    use Dispatchable;

    public ?int $actorId = null;

    public function __construct(
        public readonly string $email,
        public readonly string $reason,
    ) {
    }
}
