<?php

namespace App\DTOs\Tenant;

class SyncTenantFeatureOverridesDTO
{
    /**
     * @param array<int, array{functionality: string, is_enabled: bool}> $overrides
     */
    public function __construct(
        public readonly array $overrides
    ) {
    }

    public static function fromArray(array $data): self
    {
        $overrides = array_map(
            fn (array $item) => [
                'functionality' => $item['functionality'],
                'is_enabled' => (bool) $item['is_enabled'],
            ],
            $data['overrides'] ?? []
        );

        return new self(overrides: $overrides);
    }
}
