<?php

namespace App\DTOs\Product;

use Illuminate\Http\UploadedFile;

class CreateProductDTO
{
    /**
     * @param array<int, ProductOptionGroupInput> $optionGroups
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $productTypeUuid,
        public readonly float $price,
        public readonly ?string $description,
        public readonly bool $isAvailable,
        public readonly ?float $surchargeRate,
        public readonly ?UploadedFile $image,
        public readonly ?string $sku,
        public readonly ?string $barcode,
        public readonly ?string $brand,
        public readonly ?string $ncm,
        public readonly ?string $cest,
        public readonly ?string $origin,
        public readonly ?string $defaultCfop,
        public readonly ?string $csosnCst,
        public readonly string $unit,
        public readonly bool $isLotControlled,
        public readonly bool $isExpiryControlled,
        public readonly bool $isSerialControlled,
        public readonly ?float $minStock,
        public readonly ?float $maxStock,
        public readonly ?float $reorderPoint,
        public readonly ?float $reorderQty,
        public readonly ?float $lastPurchaseCost,
        public readonly array $optionGroups,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            productTypeUuid: $data['product_type_uuid'],
            price: (float) $data['price'],
            description: $data['description'] ?? null,
            isAvailable: array_key_exists('is_available', $data) ? filter_var($data['is_available'], FILTER_VALIDATE_BOOLEAN) : true,
            surchargeRate: isset($data['surcharge_rate']) ? (float) $data['surcharge_rate'] : null,
            image: $data['image'] ?? null,
            sku: $data['sku'] ?? null,
            barcode: $data['barcode'] ?? null,
            brand: $data['brand'] ?? null,
            ncm: $data['ncm'] ?? null,
            cest: $data['cest'] ?? null,
            origin: isset($data['origin']) ? (string) $data['origin'] : null,
            defaultCfop: $data['default_cfop'] ?? null,
            csosnCst: $data['csosn_cst'] ?? null,
            unit: $data['unit'] ?? 'un',
            isLotControlled: array_key_exists('is_lot_controlled', $data) ? filter_var($data['is_lot_controlled'], FILTER_VALIDATE_BOOLEAN) : false,
            isExpiryControlled: array_key_exists('is_expiry_controlled', $data) ? filter_var($data['is_expiry_controlled'], FILTER_VALIDATE_BOOLEAN) : false,
            isSerialControlled: array_key_exists('is_serial_controlled', $data) ? filter_var($data['is_serial_controlled'], FILTER_VALIDATE_BOOLEAN) : false,
            minStock: isset($data['min_stock']) ? (float) $data['min_stock'] : null,
            maxStock: isset($data['max_stock']) ? (float) $data['max_stock'] : null,
            reorderPoint: isset($data['reorder_point']) ? (float) $data['reorder_point'] : null,
            reorderQty: isset($data['reorder_qty']) ? (float) $data['reorder_qty'] : null,
            lastPurchaseCost: isset($data['last_purchase_cost']) ? (float) $data['last_purchase_cost'] : null,
            optionGroups: collect($data['option_groups'] ?? [])
                ->map(fn (mixed $group) => ProductOptionGroupInput::fromArray(is_array($group) ? $group : []))
                ->all(),
        );
    }
}
