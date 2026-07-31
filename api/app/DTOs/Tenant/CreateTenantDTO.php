<?php

namespace App\DTOs\Tenant;

use Illuminate\Http\UploadedFile;

class CreateTenantDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $planUuid,
        public readonly bool $isActive,
        public readonly string $trialEndsAt,
        public readonly ?UploadedFile $logo,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $trialDays = config('saas.trial_days', 30);

        return new self(
            name: $data['name'],
            slug: $data['slug'],
            planUuid: $data['plan_uuid'] ?? null,
            isActive: $data['is_active'] ?? true,
            trialEndsAt: now()->addDays($trialDays)->toDateTimeString(),
            logo: $data['logo'] ?? null,
        );
    }
}
