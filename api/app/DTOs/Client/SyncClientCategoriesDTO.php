<?php

namespace App\DTOs\Client;

class SyncClientCategoriesDTO
{
    public function __construct(
        public readonly array $categoryUuids,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            categoryUuids: $data['category_uuids'] ?? [],
        );
    }
}
