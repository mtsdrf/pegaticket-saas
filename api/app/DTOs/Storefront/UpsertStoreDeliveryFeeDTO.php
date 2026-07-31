<?php

namespace App\DTOs\Storefront;

class UpsertStoreDeliveryFeeDTO
{
    public function __construct(
        public readonly string $bairroUuid,
        public readonly float $fee,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            bairroUuid: $data['bairro_uuid'],
            fee: (float) $data['fee'],
        );
    }
}
