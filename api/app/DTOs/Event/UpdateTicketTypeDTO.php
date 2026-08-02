<?php

namespace App\DTOs\Event;

use Illuminate\Http\UploadedFile;

class UpdateTicketTypeDTO
{
    public function __construct(
        public readonly ?string $eventUuid,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?float $price,
        public readonly ?UploadedFile $image,
        public readonly ?int $quantityAvailable,
        public readonly ?int $minPerOrder,
        public readonly ?int $maxPerOrder,
        public readonly ?string $salesStartAt,
        public readonly ?string $salesEndAt,
        public readonly ?string $status,
        public readonly ?bool $reentryEnabled,
        public readonly ?int $maxReentries,
        public readonly ?int $reentryCooldownMinutes,
        public readonly ?string $sku,
        public readonly ?string $barcode,
        public readonly ?string $brand,
        public readonly ?string $unit,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            eventUuid: $data['event_uuid'] ?? null,
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            image: $data['image'] ?? null,
            quantityAvailable: array_key_exists('quantity_available', $data) ? ($data['quantity_available'] !== null ? (int) $data['quantity_available'] : null) : null,
            minPerOrder: isset($data['min_per_order']) ? (int) $data['min_per_order'] : null,
            maxPerOrder: array_key_exists('max_per_order', $data) ? ($data['max_per_order'] !== null ? (int) $data['max_per_order'] : null) : null,
            salesStartAt: $data['sales_start_at'] ?? null,
            salesEndAt: $data['sales_end_at'] ?? null,
            status: $data['status'] ?? null,
            reentryEnabled: array_key_exists('reentry_enabled', $data) ? (bool) $data['reentry_enabled'] : null,
            maxReentries: array_key_exists('max_reentries', $data) ? ($data['max_reentries'] !== null ? (int) $data['max_reentries'] : null) : null,
            reentryCooldownMinutes: array_key_exists('reentry_cooldown_minutes', $data) ? ($data['reentry_cooldown_minutes'] !== null ? (int) $data['reentry_cooldown_minutes'] : null) : null,
            sku: $data['sku'] ?? null,
            barcode: $data['barcode'] ?? null,
            brand: $data['brand'] ?? null,
            unit: $data['unit'] ?? null,
        );
    }
}
