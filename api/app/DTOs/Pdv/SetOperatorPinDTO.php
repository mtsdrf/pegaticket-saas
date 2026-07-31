<?php

namespace App\DTOs\Pdv;

class SetOperatorPinDTO
{
    public function __construct(
        public readonly string $pin,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            pin: (string) $data['pin'],
        );
    }
}
