<?php

namespace App\DTOs\Event;

class UpdateEventGateDTO
{
    /**
     * @param  string[]|null  $ticketTypeUuids  null = campo não veio no
     *                                          request (não mexe na restrição atual); array (mesmo vazio) = substitui
     *                                          a restrição pela lista informada, array vazio = volta a aceitar
     *                                          qualquer tipo de ingresso.
     */
    public function __construct(
        public readonly ?string $name,
        public readonly ?bool $isActive,
        public readonly ?array $ticketTypeUuids,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            ticketTypeUuids: array_key_exists('ticket_type_uuids', $data) ? $data['ticket_type_uuids'] : null,
        );
    }
}
