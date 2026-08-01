<?php

namespace App\Events\Ticket;

class TicketsCancelled
{
    /**
     * @param string[] $ticketUuids
     */
    public function __construct(
        public string $orderUuid,
        public array $ticketUuids,
        public string $status,
        public ?int $actorId
    ) {
    }
}
