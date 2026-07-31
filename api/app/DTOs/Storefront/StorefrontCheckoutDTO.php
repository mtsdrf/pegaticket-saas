<?php

namespace App\DTOs\Storefront;

class StorefrontCheckoutDTO
{
    /**
     * @param array<int, array{product_uuid: string, quantity: float, notes?: string, options?: array<int, array{product_option_uuid: string, quantity?: int}>}> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly string $clientName,
        public readonly string $clientLastName,
        public readonly string $clientPhone,
        public readonly ?string $notes,
        // Nullable: obrigatórios só quando fulfillmentType='delivery'
        // (validado em StorefrontCheckoutRequest via required_if) — pickup
        // não exige endereço de entrega.
        public readonly ?string $estadoUuid,
        public readonly ?string $cidadeUuid,
        public readonly ?string $bairroUuid,
        public readonly ?string $logradouro,
        public readonly ?string $numero,
        public readonly ?string $complemento,
        public readonly ?string $cep,
        public readonly ?string $couponCode = null,
        public readonly bool $useCashback = false,
        public readonly string $fulfillmentType = 'delivery',
        public readonly ?string $paymentMethod = null,
        public readonly bool $needsChange = false,
        public readonly ?float $changeForAmount = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            items: $data['items'],
            clientName: $data['client_name'],
            clientLastName: $data['client_last_name'],
            clientPhone: $data['client_phone'],
            notes: $data['notes'] ?? null,
            estadoUuid: $data['estado_uuid'] ?? null,
            cidadeUuid: $data['cidade_uuid'] ?? null,
            bairroUuid: $data['bairro_uuid'] ?? null,
            logradouro: $data['logradouro'] ?? null,
            numero: $data['numero'] ?? null,
            complemento: $data['complemento'] ?? null,
            cep: $data['cep'] ?? null,
            couponCode: $data['coupon_code'] ?? null,
            useCashback: (bool) ($data['use_cashback'] ?? false),
            fulfillmentType: $data['fulfillment_type'] ?? 'delivery',
            paymentMethod: $data['payment_method'] ?? null,
            needsChange: (bool) ($data['needs_change'] ?? false),
            changeForAmount: isset($data['change_for_amount']) ? (float) $data['change_for_amount'] : null,
        );
    }
}
