<?php

namespace App\Events\Ticket;

class TicketResaleListingCreated
{
    public function __construct(
        public string $listingUuid,
        public string $ticketUuid,
        public float $askingPrice,
        public ?string $sellerFinalCustomerUuid,
    ) {}
}
