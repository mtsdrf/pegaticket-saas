<?php

namespace App\DTOs\Balcao;

class OpenComandaDTO
{
    public function __construct(
        public readonly ?string $tableUuid,
        public readonly ?string $label,
        public readonly ?string $clientComandaUuid,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tableUuid: $data['table_uuid'] ?? null,
            label: $data['label'] ?? null,
            clientComandaUuid: $data['client_comanda_uuid'] ?? null,
        );
    }
}
