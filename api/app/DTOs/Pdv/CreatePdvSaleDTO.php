<?php

namespace App\DTOs\Pdv;

class CreatePdvSaleDTO
{
    /**
     * @param array<int, array{product_uuid: string, quantity: float, unit_price?: float}> $items
     *   Mesmo shape dos itens de OrderService::create() — unit_price opcional
     *   (resolvido pelo ProductPricingService quando ausente).
     * @param array<int, array{method: string, amount: float}> $payments
     *   Formas de pagamento declaradas pelo operador no fechamento. A soma
     *   precisa bater EXATAMENTE com o total do pedido calculado no backend.
     */
    public function __construct(
        public readonly ?string $clientUuid,
        public readonly ?string $stockLocationUuid,
        public readonly ?string $notes,
        public readonly array $items,
        public readonly array $payments,
        public readonly ?string $operatorUuid = null,
        public readonly ?string $clientSaleUuid = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            clientUuid: $data['client_uuid'] ?? null,
            stockLocationUuid: $data['stock_location_uuid'] ?? null,
            notes: $data['notes'] ?? null,
            items: $data['items'],
            payments: $data['payments'],
            operatorUuid: $data['operator_uuid'] ?? null,
            clientSaleUuid: $data['client_sale_uuid'] ?? null,
        );
    }
}
