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
        public readonly ?string $venueUuid,
        public readonly ?string $descriptionShort,
        public readonly ?string $descriptionFull,
        public readonly ?UploadedFile $coverImage,
        public readonly string $type,
        public readonly ?string $locationName,
        public readonly ?string $locationAddress,
        public readonly ?float $locationLat,
        public readonly ?float $locationLng,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly string $visibility,
        public readonly string $status,
        public readonly bool $reentryEnabled,
        public readonly ?int $maxReentries,
        public readonly ?int $reentryCooldownMinutes,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            slug: $data['slug'],
            eventCategoryUuid: $data['event_category_uuid'] ?? null,
            venueUuid: $data['venue_uuid'] ?? null,
            descriptionShort: $data['description_short'] ?? null,
            descriptionFull: $data['description_full'] ?? null,
            coverImage: $data['cover_image'] ?? null,
            type: $data['type'] ?? 'ingresso',
            locationName: $data['location_name'] ?? null,
            locationAddress: $data['location_address'] ?? null,
            locationLat: isset($data['location_lat']) ? (float) $data['location_lat'] : null,
            locationLng: isset($data['location_lng']) ? (float) $data['location_lng'] : null,
            startsAt: $data['starts_at'],
            endsAt: $data['ends_at'],
            visibility: $data['visibility'] ?? 'public',
            status: $data['status'] ?? 'rascunho',
            reentryEnabled: (bool) ($data['reentry_enabled'] ?? false),
            maxReentries: isset($data['max_reentries']) ? (int) $data['max_reentries'] : null,
            reentryCooldownMinutes: isset($data['reentry_cooldown_minutes']) ? (int) $data['reentry_cooldown_minutes'] : null,
        );
    }
}
