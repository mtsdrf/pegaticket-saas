<?php

namespace App\DTOs\Storefront;

class UpdateStoreBusinessHoursDTO
{
    /**
     * @param array<int, array{day_of_week:int, opens_at:?string, closes_at:?string, is_closed:bool}> $days
     */
    public function __construct(
        public readonly array $days,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(days: $data['days']);
    }
}
