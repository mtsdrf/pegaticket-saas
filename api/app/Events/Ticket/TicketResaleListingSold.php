<?php

namespace App\Events\Ticket;

class TicketResaleListingSold
{
    public function __construct(
        public string $listingUuid,
        public string $ticketUuid,
        public float $askingPrice,
        public ?string $sellerFinalCustomerUuid,
        public ?string $buyerFinalCustomerUuid,
    ) {}
}
