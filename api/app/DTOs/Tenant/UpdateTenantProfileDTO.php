<?php

namespace App\DTOs\Tenant;

use Illuminate\Http\UploadedFile;

class UpdateTenantProfileDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?UploadedFile $logo,
        public readonly ?string $cnpj,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            logo: $data['logo'] ?? null,
            cnpj: $data['cnpj'] ?? null,
        );
    }
}
