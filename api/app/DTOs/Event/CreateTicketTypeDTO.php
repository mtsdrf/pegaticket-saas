<?php

namespace App\DTOs\Event;

use Illuminate\Http\UploadedFile;

class CreateTicketTypeDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $eventUuid,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly ?UploadedFile $image,
        public readonly ?int $quantityAvailable,
        public readonly int $minPerOrder,
        public readonly ?int $maxPerOrder,
        public readonly ?string $salesStartAt,
        public readonly ?string $salesEndAt,
        public readonly string $status,
        public readonly ?string $sku,
        public readonly ?string $barcode,
        public readonly ?string $brand,
        public readonly string $unit,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            eventUuid: $data['event_uuid'],
            name: $data['name'],
            description: $data['description'] ?? null,
            price: (float) $data['price'],
            image: $data['image'] ?? null,
            quantityAvailable: isset($data['quantity_available']) ? (int) $data['quantity_available'] : null,
            minPerOrder: isset($data['min_per_order']) ? (int) $data['min_per_order'] : 1,
            maxPerOrder: isset($data['max_per_order']) ? (int) $data['max_per_order'] : null,
            salesStartAt: $data['sales_start_at'] ?? null,
            salesEndAt: $data['sales_end_at'] ?? null,
            status: $data['status'] ?? 'rascunho',
            sku: $data['sku'] ?? null,
            barcode: $data['barcode'] ?? null,
            brand: $data['brand'] ?? null,
            unit: $data['unit'] ?? 'un',
        );
    }
}
