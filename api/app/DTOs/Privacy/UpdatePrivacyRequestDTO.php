<?php

namespace App\DTOs\Privacy;

class UpdatePrivacyRequestDTO
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $resolutionNotes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: (string) $data['status'],
            resolutionNotes: isset($data['resolution_notes']) ? trim((string) $data['resolution_notes']) : null,
        );
    }
}
