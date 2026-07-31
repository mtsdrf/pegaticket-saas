<?php

namespace App\DTOs\Venue;

use Illuminate\Http\UploadedFile;

class CreateVenueDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?UploadedFile $backgroundImage,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            backgroundImage: $data['background_image'] ?? null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
