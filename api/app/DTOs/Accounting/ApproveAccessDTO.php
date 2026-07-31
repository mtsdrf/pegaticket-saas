<?php

namespace App\DTOs\Accounting;

class ApproveAccessDTO
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly array $scopes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            scopes: array_values(array_unique($data['scopes'] ?? [])),
        );
    }
}
