<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketResaleListingCancelled;
use App\Models\AuditLog;

class AuditTicketResaleListingCancelled
{
    public function handle(TicketResaleListingCancelled $event): void
    {
        AuditLog::record(
            event: 'ticket_resale_listing_cancelled',
            model: null,
            meta: [
                'listing_uuid' => $event->listingUuid,
                'ticket_uuid' => $event->ticketUuid,
                'seller_final_customer_uuid' => $event->sellerFinalCustomerUuid,
            ],
            actorId: null
        );
    }
}
