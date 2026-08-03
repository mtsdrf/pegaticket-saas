<?php

namespace App\Events\GuestList;

class GuestListEntryRedeemed
{
    public function __construct(
        public string $guestListEntryUuid,
        public string $saleUuid,
    ) {}
}
