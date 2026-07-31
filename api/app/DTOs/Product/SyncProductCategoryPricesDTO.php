<?php

namespace App\DTOs\Product;

class SyncProductCategoryPricesDTO
{
    /**
     * @param array<int, array{client_category_uuid: string, price: float}> $prices
     */
    public function __construct(
        public readonly array $prices,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            prices: $data['prices'] ?? [],
        );
    }
}
