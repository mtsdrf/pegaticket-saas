<?php

namespace App\DTOs\Tenant;

use Illuminate\Http\UploadedFile;

class UpdateTenantDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $planUuid,
        public readonly bool $isActive,
        public readonly ?UploadedFile $logo,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            planUuid: $data['plan_uuid'] ?? null,
            isActive: $data['is_active'],
            logo: $data['logo'] ?? null,
        );
    }
}
