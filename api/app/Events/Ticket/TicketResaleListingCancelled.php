<?php

namespace App\Events\Ticket;

class TicketResaleListingCancelled
{
    public function __construct(
        public string $listingUuid,
        public string $ticketUuid,
        public ?string $sellerFinalCustomerUuid,
    ) {}
}
