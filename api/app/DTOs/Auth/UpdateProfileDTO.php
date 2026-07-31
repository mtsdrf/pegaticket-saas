<?php

namespace App\DTOs\Auth;

use Illuminate\Http\UploadedFile;

class UpdateProfileDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly ?UploadedFile $avatar,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            phone: $data['phone'] ?? null,
            avatar: $data['avatar'] ?? null,
        );
    }
}
