<?php

namespace App\DTOs\Event;

class CreateTicketTypeChannelQuotaDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $ticketTypeUuid,
        public readonly string $channel,
        public readonly int $quota,
    ) {}

    public static function fromArray(array $data, int $tenantId, string $ticketTypeUuid): self
    {
        return new self(
            tenantId: $tenantId,
            ticketTypeUuid: $ticketTypeUuid,
            channel: $data['channel'],
            quota: (int) $data['quota'],
        );
    }
}
