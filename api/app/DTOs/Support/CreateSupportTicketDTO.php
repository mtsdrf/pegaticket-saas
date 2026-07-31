<?php

namespace App\DTOs\Support;

class CreateSupportTicketDTO
{
    public function __construct(
        public readonly string $subject,
        public readonly string $description,
        public readonly bool $includeDiagnostics,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subject: $data['subject'],
            description: $data['description'],
            includeDiagnostics: filter_var($data['include_diagnostics'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }
}
