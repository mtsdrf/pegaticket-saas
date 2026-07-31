<?php

namespace App\DTOs\Legal;

class UpdateReleaseNoteDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $version,
        public readonly ?string $publishedAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            body: $data['body'],
            version: $data['version'] ?? null,
            publishedAt: $data['published_at'] ?? null,
        );
    }
}
