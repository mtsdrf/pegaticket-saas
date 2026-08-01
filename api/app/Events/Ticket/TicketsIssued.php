<?php

namespace App\Events\Ticket;

class TicketsIssued
{
    /**
     * @param string[] $ticketUuids
     */
    public function __construct(
        public string $orderUuid,
        public array $ticketUuids,
        public ?int $actorId
    ) {
    }
}
