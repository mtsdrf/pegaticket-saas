<?php

namespace App\DTOs\Event;

class UpdateTicketTypeChannelQuotaDTO
{
    public function __construct(
        public readonly ?int $quota,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            quota: isset($data['quota']) ? (int) $data['quota'] : null,
        );
    }
}
