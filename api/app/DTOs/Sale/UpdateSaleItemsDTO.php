<?php

namespace App\DTOs\Sale;

class UpdateSaleItemsDTO
{
    /**
     * @param array<int, array{uuid?: string, ticket_type_uuid?: string, event_product_uuid?: string, quantity: float, unit_price?: float}> $items
     *   uuid presente = item existente do pedido (atualiza); uuid ausente
     *   = item novo (cria). Item existente ausente deste array é removido.
     *   Exatamente um de ticket_type_uuid/event_product_uuid por item.
     */
    public function __construct(
        public readonly ?string $notes,
        public readonly ?string $stockLocationUuid,
        public readonly ?string $expectedDeliveryDate,
        public readonly array $items,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            notes: $data['notes'] ?? null,
            stockLocationUuid: $data['stock_location_uuid'] ?? null,
            expectedDeliveryDate: $data['expected_delivery_date'] ?? null,
            items: $data['items'],
        );
    }
}
