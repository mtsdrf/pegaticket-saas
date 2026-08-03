<?php

namespace App\Listeners\GuestList;

use App\Events\GuestList\GuestListEntryRedeemed;
use App\Models\AuditLog;

class AuditGuestListEntryRedeemed
{
    public function handle(GuestListEntryRedeemed $event): void
    {
        AuditLog::record(
            event: 'guest_list_entry_redeemed',
            model: null,
            meta: [
                'guest_list_entry_uuid' => $event->guestListEntryUuid,
                'sale_uuid' => $event->saleUuid,
            ],
            actorId: null
        );
    }
}
