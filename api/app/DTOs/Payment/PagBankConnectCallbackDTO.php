<?php

namespace App\DTOs\Payment;

class PagBankConnectCallbackDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $state,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            state: $data['state'],
        );
    }
}
