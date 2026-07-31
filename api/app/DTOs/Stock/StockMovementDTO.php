<?php

namespace App\DTOs\Stock;

/**
 * Forma genérica compartilhada pelas movimentações que só precisam de
 * produto+local+quantidade+motivo: entry, exit, return, loss, block,
 * unblock, reserve. Validação específica de cada tipo continua no
 * respectivo FormRequest — este DTO só carrega o já validado.
 *
 * unitCost só é preenchido por StoreStockMovementEntryRequest (CMV real,
 * roadmap A3.13) — os demais tipos não o enviam, fica null.
 */
class StockMovementDTO
{
    public function __construct(
        public readonly string $productUuid,
        public readonly string $locationUuid,
        public readonly float $quantity,
        public readonly string $reason,
        public readonly ?string $notes,
        public readonly ?float $unitCost = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productUuid: $data['product_uuid'],
            locationUuid: $data['location_uuid'],
            quantity: (float) $data['quantity'],
            reason: $data['reason'],
            notes: $data['notes'] ?? null,
            unitCost: isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
        );
    }
}
