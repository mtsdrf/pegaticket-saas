<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketResaleListingCreated;
use App\Models\AuditLog;

class AuditTicketResaleListingCreated
{
    public function handle(TicketResaleListingCreated $event): void
    {
        AuditLog::record(
            event: 'ticket_resale_listing_created',
            model: null,
            meta: [
                'listing_uuid' => $event->listingUuid,
                'ticket_uuid' => $event->ticketUuid,
                'asking_price' => $event->askingPrice,
                'seller_final_customer_uuid' => $event->sellerFinalCustomerUuid,
            ],
            actorId: null
        );
    }
}
