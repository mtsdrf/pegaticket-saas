<?php

namespace App\DTOs\GuestList;

class AddGuestListEntryDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $document,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            document: $data['document'] ?? null,
        );
    }
}
