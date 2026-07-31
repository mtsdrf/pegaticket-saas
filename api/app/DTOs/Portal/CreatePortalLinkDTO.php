<?php

namespace App\DTOs\Portal;

class CreatePortalLinkDTO
{
    public function __construct(
        public readonly string $orderUuid,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            orderUuid: $data['order_uuid'],
        );
    }
}
