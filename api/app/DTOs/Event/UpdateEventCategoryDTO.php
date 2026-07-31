<?php

namespace App\DTOs\Event;

class UpdateEventCategoryDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?int $priority,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            priority: $data['priority'] ?? null,
            isActive: $data['is_active'] ?? null,
        );
    }
}
