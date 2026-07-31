<?php

namespace App\DTOs\Product;

class ProductOptionGroupInput
{
    /**
     * @param array<int, ProductOptionInput> $options
     */
    public function __construct(
        public readonly ?string $uuid,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $minSelect,
        public readonly int $maxSelect,
        public readonly int $sortOrder,
        public readonly bool $isActive,
        public readonly array $options,
        // 'addon' (default, "escolha com preço") | 'ingredient_removal'
        // (frontend renderiza checkbox "remover X" em vez de "+ adicionar
        // X") — semântica pro frontend, mesma estrutura de dados.
        public readonly string $kind = 'addon',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            name: trim((string) $data['name']),
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            minSelect: (int) ($data['min_select'] ?? 0),
            maxSelect: (int) ($data['max_select'] ?? 0),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isActive: array_key_exists('is_active', $data) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
            options: collect($data['options'] ?? [])
                ->map(fn (mixed $option) => ProductOptionInput::fromArray(is_array($option) ? $option : []))
                ->all(),
            kind: $data['kind'] ?? 'addon',
        );
    }
}
