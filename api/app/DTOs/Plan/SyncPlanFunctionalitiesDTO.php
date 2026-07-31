<?php

namespace App\DTOs\Plan;

class SyncPlanFunctionalitiesDTO
{
    public function __construct(
        public readonly array $functionalities
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            functionalities: array_values(array_unique($data['functionalities'] ?? []))
        );
    }
}
