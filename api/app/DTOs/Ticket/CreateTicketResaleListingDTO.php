<?php

namespace App\DTOs\Ticket;

class CreateTicketResaleListingDTO
{
    public function __construct(
        public readonly float $askingPrice,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            askingPrice: (float) $data['asking_price'],
        );
    }
}
