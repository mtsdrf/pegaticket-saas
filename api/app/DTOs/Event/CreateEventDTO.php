<?php

namespace App\DTOs\Event;

use Illuminate\Http\UploadedFile;

class CreateEventDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $eventCategoryUuid,
        public readonly ?string $descriptionShort,
        public readonly ?string $descriptionFull,
        public readonly ?UploadedFile $coverImage,
        public readonly string $type,
        public readonly ?string $locationName,
        public readonly ?string $locationAddress,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly string $visibility,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            slug: $data['slug'],
            eventCategoryUuid: $data['event_category_uuid'] ?? null,
            descriptionShort: $data['description_short'] ?? null,
            descriptionFull: $data['description_full'] ?? null,
            coverImage: $data['cover_image'] ?? null,
            type: $data['type'] ?? 'ingresso',
            locationName: $data['location_name'] ?? null,
            locationAddress: $data['location_address'] ?? null,
            startsAt: $data['starts_at'],
            endsAt: $data['ends_at'],
            visibility: $data['visibility'] ?? 'public',
            status: $data['status'] ?? 'rascunho',
        );
    }
}
