<?php

namespace App\Events\Accounting;

use Illuminate\Foundation\Events\Dispatchable;

class AccountingAccessRequested
{
    use Dispatchable;

    public ?int $actorId = null;

    public function __construct(
        public readonly string $linkUuid,
        public readonly string $accountingOfficeUuid,
        public readonly int $tenantId,
    ) {
    }
}
