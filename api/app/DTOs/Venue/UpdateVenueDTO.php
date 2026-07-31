<?php

namespace App\DTOs\Venue;

use Illuminate\Http\UploadedFile;

class UpdateVenueDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?UploadedFile $backgroundImage,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            backgroundImage: $data['background_image'] ?? null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        );
    }
}
