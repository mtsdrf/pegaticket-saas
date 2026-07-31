<?php

namespace App\DTOs\Event;

use Illuminate\Http\UploadedFile;

class UpdateEventDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $slug,
        public readonly ?string $eventCategoryUuid,
        public readonly ?string $descriptionShort,
        public readonly ?string $descriptionFull,
        public readonly ?UploadedFile $coverImage,
        public readonly ?string $type,
        public readonly ?string $locationName,
        public readonly ?string $locationAddress,
        public readonly ?string $startsAt,
        public readonly ?string $endsAt,
        public readonly ?string $visibility,
        public readonly ?string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            eventCategoryUuid: $data['event_category_uuid'] ?? null,
            descriptionShort: $data['description_short'] ?? null,
            descriptionFull: $data['description_full'] ?? null,
            coverImage: $data['cover_image'] ?? null,
            type: $data['type'] ?? null,
            locationName: $data['location_name'] ?? null,
            locationAddress: $data['location_address'] ?? null,
            startsAt: $data['starts_at'] ?? null,
            endsAt: $data['ends_at'] ?? null,
            visibility: $data['visibility'] ?? null,
            status: $data['status'] ?? null,
        );
    }
}
