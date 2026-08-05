<?php

namespace App\DTOs\EmailTemplate;

class UpsertEmailTemplateDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $type,
        public readonly ?string $subject,
        public readonly ?string $bodyHtml,
    ) {}

    public static function fromArray(array $data, int $tenantId, string $type): self
    {
        return new self(
            tenantId: $tenantId,
            type: $type,
            subject: $data['subject'] ?? null,
            bodyHtml: $data['body_html'] ?? null,
        );
    }
}
