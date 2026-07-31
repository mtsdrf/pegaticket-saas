<?php

namespace App\Events\Accounting;

use Illuminate\Foundation\Events\Dispatchable;

class AccountingAccessApproved
{
    use Dispatchable;

    public function __construct(
        public readonly string $linkUuid,
        public readonly int $tenantId,
        public readonly array $scopes,
        public readonly ?int $actorId,
    ) {
    }
}
