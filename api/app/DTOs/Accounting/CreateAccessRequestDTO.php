<?php

namespace App\DTOs\Accounting;

use App\Support\BrazilDocument;

class CreateAccessRequestDTO
{
    public function __construct(
        public readonly string $tenantCnpj,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tenantCnpj: (string) BrazilDocument::normalizeCnpj($data['tenant_cnpj']),
        );
    }
}
