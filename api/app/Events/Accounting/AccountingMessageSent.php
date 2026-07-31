<?php

namespace App\Events\Accounting;

use Illuminate\Foundation\Events\Dispatchable;

class AccountingMessageSent
{
    use Dispatchable;

    public function __construct(
        public readonly string $messageUuid,
        public readonly string $linkUuid,
        public readonly string $senderType,
        public readonly ?int $actorId,
    ) {
    }
}
