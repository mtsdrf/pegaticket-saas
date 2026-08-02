<?php

namespace App\DTOs\Portal;

class CreatePortalLinkDTO
{
    public function __construct(
        public readonly string $saleUuid,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            saleUuid: $data['sale_uuid'],
        );
    }
}
