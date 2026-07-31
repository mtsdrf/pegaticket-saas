<?php

namespace App\DTOs\Auth;

class CreateSelfSignupDTO
{
    public function __construct(
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly string $password,
        public readonly string $tenantName,
        public readonly string $tenantSlug,
        public readonly ?string $planUuid,
        public readonly bool $termsAccepted,
        public readonly bool $privacyAccepted,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ownerName: $data['owner_name'],
            ownerEmail: $data['owner_email'],
            password: $data['password'],
            tenantName: $data['tenant_name'],
            tenantSlug: $data['tenant_slug'],
            planUuid: $data['plan_uuid'] ?? null,
            termsAccepted: (bool) ($data['terms_accepted'] ?? false),
            privacyAccepted: (bool) ($data['privacy_accepted'] ?? false),
        );
    }
}
