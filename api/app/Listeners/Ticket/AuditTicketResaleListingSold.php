<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketResaleListingSold;
use App\Models\AuditLog;

class AuditTicketResaleListingSold
{
    public function handle(TicketResaleListingSold $event): void
    {
        AuditLog::record(
            event: 'ticket_resale_listing_sold',
            model: null,
            meta: [
                'listing_uuid' => $event->listingUuid,
                'ticket_uuid' => $event->ticketUuid,
                'asking_price' => $event->askingPrice,
                'seller_final_customer_uuid' => $event->sellerFinalCustomerUuid,
                'buyer_final_customer_uuid' => $event->buyerFinalCustomerUuid,
            ],
            actorId: null
        );
    }
}
