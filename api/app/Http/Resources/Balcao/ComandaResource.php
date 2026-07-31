<?php

namespace App\Http\Resources\Balcao;

use Illuminate\Http\Resources\Json\JsonResource;

class ComandaResource extends JsonResource
{
    public function toArray($request): array
    {
        $activeItems = $this->whenLoaded('items', fn() => $this->items
            ->where('prep_status', '!=', 'cancelled'));

        return [
            'uuid' => $this->uuid,
            'label' => $this->label,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'service_fee_percent' => $this->service_fee_percent !== null ? (float) $this->service_fee_percent : null,
            'order_uuid' => $this->whenLoaded('order', fn() => $this->order?->uuid),
            'table' => $this->whenLoaded('table', fn() => $this->table ? [
                'uuid' => $this->table->uuid,
                'label' => $this->table->label,
            ] : null),
            'items' => ComandaItemResource::collection($this->whenLoaded('items')),
            // Subtotal dos itens não cancelados (conveniência para a tela do
            // garçom); a taxa de serviço e o total final são resolvidos no
            // fechamento (ComandaService::close).
            'items_subtotal' => $this->whenLoaded('items', fn() => round(
                $this->items
                    ->where('prep_status', '!=', 'cancelled')
                    ->sum(fn($item) => (float) $item->qty * (float) $item->unit_price),
                2
            )),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
