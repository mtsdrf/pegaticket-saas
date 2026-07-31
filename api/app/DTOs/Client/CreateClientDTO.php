<?php

namespace App\DTOs\Client;

use App\Support\BrazilDocument;

class CreateClientDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $phonePrimary,
        public readonly ?string $phoneSecondary,
        public readonly ?string $notes,
        public readonly bool $isTrusted,
        public readonly bool $isActive,
        // Endereço criado inline (sem tela de endereço separada, replicando o legado).
        public readonly string $estadoUuid,
        public readonly string $cidadeUuid,
        public readonly string $bairroUuid,
        public readonly string $logradouro,
        public readonly ?string $numero,
        public readonly ?string $complemento,
        public readonly ?string $cep,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        // Cadastro fiscal do destinatário (roadmap Fiscal D0) — opcionais, ao
        // final para não quebrar chamadas posicionais/nomeadas existentes
        // (ex: StorefrontCheckoutService, que não informa dado fiscal).
        public readonly ?string $cpfCnpj = null,
        public readonly ?string $ie = null,
        public readonly ?string $ieIndicator = null,
        // Sobrenome (checkout público) — ao final por mesmo motivo dos
        // campos fiscais acima: não quebrar chamadas posicionais/nomeadas
        // existentes. Nullable: cadastro pelo staff não exige sobrenome.
        public readonly ?string $lastName = null,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            cpfCnpj: BrazilDocument::normalizeCpfOrCnpj($data['cpf_cnpj'] ?? null),
            ie: $data['ie'] ?? null,
            ieIndicator: $data['ie_indicator'] ?? null,
            phonePrimary: $data['phone_primary'] ?? null,
            phoneSecondary: $data['phone_secondary'] ?? null,
            notes: $data['notes'] ?? null,
            isTrusted: $data['is_trusted'] ?? true,
            isActive: $data['is_active'] ?? true,
            estadoUuid: $data['estado_uuid'],
            cidadeUuid: $data['cidade_uuid'],
            bairroUuid: $data['bairro_uuid'],
            logradouro: $data['logradouro'],
            numero: $data['numero'] ?? null,
            complemento: $data['complemento'] ?? null,
            cep: $data['cep'] ?? null,
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
            lastName: $data['last_name'] ?? null,
        );
    }
}
