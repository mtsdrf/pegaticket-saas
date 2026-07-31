<?php

namespace App\Events\Accounting;

use Illuminate\Foundation\Events\Dispatchable;

class AccountingLoginSucceeded
{
    use Dispatchable;

    public ?int $actorId = null;

    public function __construct(
        public readonly string $accountingOfficeUuid,
    ) {
    }
}
