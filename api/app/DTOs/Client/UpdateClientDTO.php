<?php

namespace App\DTOs\Client;

use App\Support\BrazilDocument;

class UpdateClientDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $cpfCnpj,
        public readonly ?string $ie,
        public readonly ?string $ieIndicator,
        public readonly ?string $phonePrimary,
        public readonly ?string $phoneSecondary,
        public readonly ?string $notes,
        public readonly ?bool $isTrusted,
        public readonly ?bool $isActive,
        // Campos de endereço inline: só preenchidos quando o request realmente
        // pede alteração do endereço vinculado (delega para EnderecoService::update).
        public readonly ?string $estadoUuid,
        public readonly ?string $cidadeUuid,
        public readonly ?string $bairroUuid,
        public readonly ?string $logradouro,
        public readonly ?string $numero,
        public readonly ?string $complemento,
        public readonly ?string $cep,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            cpfCnpj: BrazilDocument::normalizeCpfOrCnpj($data['cpf_cnpj'] ?? null),
            ie: $data['ie'] ?? null,
            ieIndicator: $data['ie_indicator'] ?? null,
            phonePrimary: $data['phone_primary'] ?? null,
            phoneSecondary: $data['phone_secondary'] ?? null,
            notes: $data['notes'] ?? null,
            isTrusted: $data['is_trusted'] ?? null,
            isActive: $data['is_active'] ?? null,
            estadoUuid: $data['estado_uuid'] ?? null,
            cidadeUuid: $data['cidade_uuid'] ?? null,
            bairroUuid: $data['bairro_uuid'] ?? null,
            logradouro: $data['logradouro'] ?? null,
            numero: $data['numero'] ?? null,
            complemento: $data['complemento'] ?? null,
            cep: $data['cep'] ?? null,
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
        );
    }

    public function hasEnderecoChanges(): bool
    {
        return $this->estadoUuid !== null
            || $this->cidadeUuid !== null
            || $this->bairroUuid !== null
            || $this->logradouro !== null
            || $this->numero !== null
            || $this->complemento !== null
            || $this->cep !== null
            || $this->lat !== null
            || $this->lng !== null;
    }
}
