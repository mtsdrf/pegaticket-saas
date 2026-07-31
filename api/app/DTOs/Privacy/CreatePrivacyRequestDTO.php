<?php

namespace App\DTOs\Privacy;

class CreatePrivacyRequestDTO
{
    public function __construct(
        public readonly string $requesterName,
        public readonly ?string $requesterEmail,
        public readonly string $requesterRole,
        public readonly string $requestType,
        public readonly ?string $channel,
        public readonly string $subject,
        public readonly string $description,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            requesterName: trim((string) $data['requester_name']),
            requesterEmail: isset($data['requester_email']) ? trim((string) $data['requester_email']) : null,
            requesterRole: (string) $data['requester_role'],
            requestType: (string) $data['request_type'],
            channel: isset($data['channel']) ? (string) $data['channel'] : null,
            subject: trim((string) $data['subject']),
            description: trim((string) $data['description']),
        );
    }
}
