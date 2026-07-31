<?php

namespace App\Http\Resources\Balcao;

use Illuminate\Http\Resources\Json\JsonResource;

class ComandaItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'qty' => (float) $this->qty,
            'unit_price' => (float) $this->unit_price,
            'line_total' => round((float) $this->qty * (float) $this->unit_price, 2),
            'notes' => $this->notes,
            'prep_status' => $this->prep_status,
            'sent_to_station_at' => $this->sent_to_station_at?->toIso8601String(),
            'preparing_at' => $this->preparing_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_reason' => $this->cancelled_reason,
            'product' => $this->whenLoaded('product', fn() => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
            ]),
            'station' => $this->whenLoaded('station', fn() => $this->station ? [
                'uuid' => $this->station->uuid,
                'name' => $this->station->name,
                'type' => $this->station->type,
            ] : null),
            // Contexto da comanda/mesa — usado pela fila do KDS (tickets) para
            // o preparador saber de que comanda/mesa é o item.
            'comanda' => $this->whenLoaded('comanda', fn() => [
                'uuid' => $this->comanda->uuid,
                'label' => $this->comanda->label,
                'table_label' => $this->comanda->relationLoaded('table') ? $this->comanda->table?->label : null,
            ]),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
