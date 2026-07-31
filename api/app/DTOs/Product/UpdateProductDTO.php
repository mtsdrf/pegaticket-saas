<?php

namespace App\DTOs\Product;

use Illuminate\Http\UploadedFile;

class UpdateProductDTO
{
    /**
     * @param array<int, ProductOptionGroupInput>|null $optionGroups
     */
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $productTypeUuid,
        public readonly ?float $price,
        public readonly ?string $description,
        public readonly ?bool $isAvailable,
        public readonly ?float $surchargeRate,
        public readonly ?UploadedFile $image,
        public readonly ?string $sku,
        public readonly ?string $barcode,
        public readonly ?string $brand,
        public readonly ?string $unit,
        public readonly ?bool $isLotControlled,
        public readonly ?bool $isExpiryControlled,
        public readonly ?bool $isSerialControlled,
        public readonly ?float $minStock,
        public readonly ?float $maxStock,
        public readonly ?float $reorderPoint,
        public readonly ?float $reorderQty,
        public readonly ?float $lastPurchaseCost,
        public readonly ?array $optionGroups,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            productTypeUuid: $data['product_type_uuid'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            description: $data['description'] ?? null,
            isAvailable: array_key_exists('is_available', $data) ? filter_var($data['is_available'], FILTER_VALIDATE_BOOLEAN) : null,
            surchargeRate: isset($data['surcharge_rate']) ? (float) $data['surcharge_rate'] : null,
            image: $data['image'] ?? null,
            sku: $data['sku'] ?? null,
            barcode: $data['barcode'] ?? null,
            brand: $data['brand'] ?? null,
            unit: $data['unit'] ?? null,
            isLotControlled: array_key_exists('is_lot_controlled', $data) ? filter_var($data['is_lot_controlled'], FILTER_VALIDATE_BOOLEAN) : null,
            isExpiryControlled: array_key_exists('is_expiry_controlled', $data) ? filter_var($data['is_expiry_controlled'], FILTER_VALIDATE_BOOLEAN) : null,
            isSerialControlled: array_key_exists('is_serial_controlled', $data) ? filter_var($data['is_serial_controlled'], FILTER_VALIDATE_BOOLEAN) : null,
            minStock: isset($data['min_stock']) ? (float) $data['min_stock'] : null,
            maxStock: isset($data['max_stock']) ? (float) $data['max_stock'] : null,
            reorderPoint: isset($data['reorder_point']) ? (float) $data['reorder_point'] : null,
            reorderQty: isset($data['reorder_qty']) ? (float) $data['reorder_qty'] : null,
            lastPurchaseCost: isset($data['last_purchase_cost']) ? (float) $data['last_purchase_cost'] : null,
            optionGroups: array_key_exists('option_groups', $data)
                ? collect($data['option_groups'] ?? [])
                    ->map(fn (mixed $group) => ProductOptionGroupInput::fromArray(is_array($group) ? $group : []))
                    ->all()
                : null,
        );
    }
}
