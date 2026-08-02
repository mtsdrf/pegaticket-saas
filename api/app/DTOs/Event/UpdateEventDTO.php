<?php

namespace App\DTOs\Event;

use Illuminate\Http\UploadedFile;

class UpdateEventDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $slug,
        public readonly ?string $eventCategoryUuid,
        public readonly ?string $venueUuid,
        public readonly bool $venueUuidProvided,
        public readonly ?string $descriptionShort,
        public readonly ?string $descriptionFull,
        public readonly ?UploadedFile $coverImage,
        public readonly ?string $type,
        public readonly ?string $locationName,
        public readonly ?string $locationAddress,
        public readonly ?float $locationLat,
        public readonly ?float $locationLng,
        public readonly ?string $startsAt,
        public readonly ?string $endsAt,
        public readonly ?string $visibility,
        public readonly ?string $status,
        public readonly ?bool $reentryEnabled,
        public readonly ?int $maxReentries,
        public readonly ?int $reentryCooldownMinutes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            eventCategoryUuid: $data['event_category_uuid'] ?? null,
            venueUuid: $data['venue_uuid'] ?? null,
            venueUuidProvided: array_key_exists('venue_uuid', $data),
            descriptionShort: $data['description_short'] ?? null,
            descriptionFull: $data['description_full'] ?? null,
            coverImage: $data['cover_image'] ?? null,
            type: $data['type'] ?? null,
            locationName: $data['location_name'] ?? null,
            locationAddress: $data['location_address'] ?? null,
            locationLat: isset($data['location_lat']) ? (float) $data['location_lat'] : null,
            locationLng: isset($data['location_lng']) ? (float) $data['location_lng'] : null,
            startsAt: $data['starts_at'] ?? null,
            endsAt: $data['ends_at'] ?? null,
            visibility: $data['visibility'] ?? null,
            status: $data['status'] ?? null,
            reentryEnabled: array_key_exists('reentry_enabled', $data) ? (bool) $data['reentry_enabled'] : null,
            maxReentries: array_key_exists('max_reentries', $data) ? ($data['max_reentries'] !== null ? (int) $data['max_reentries'] : null) : null,
            reentryCooldownMinutes: array_key_exists('reentry_cooldown_minutes', $data) ? ($data['reentry_cooldown_minutes'] !== null ? (int) $data['reentry_cooldown_minutes'] : null) : null,
        );
    }
}
