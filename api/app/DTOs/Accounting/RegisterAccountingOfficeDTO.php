<?php

namespace App\DTOs\Accounting;

use App\Support\BrazilDocument;

class RegisterAccountingOfficeDTO
{
    public function __construct(
        public readonly string $cnpj,
        public readonly string $companyName,
        public readonly string $responsibleName,
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cnpj: (string) BrazilDocument::normalizeCnpj($data['cnpj']),
            companyName: $data['company_name'],
            responsibleName: $data['responsible_name'],
            email: $data['email'],
            password: $data['password'],
        );
    }
}
