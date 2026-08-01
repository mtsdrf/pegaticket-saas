<?php

namespace App\DTOs\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;

class CheckinResultDTO
{
    public function __construct(
        public readonly string $result,
        public readonly ?Ticket $ticket,
        public readonly ?TicketCheckin $checkin,
    ) {
    }
}
