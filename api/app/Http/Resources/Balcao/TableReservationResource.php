<?php

namespace App\Http\Resources\Balcao;

use Illuminate\Http\Resources\Json\JsonResource;

class TableReservationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'party_size' => $this->party_size,
            'scheduled_for' => $this->scheduled_for,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'cancelled_reason' => $this->cancelled_reason,
            'confirmed_at' => $this->confirmed_at,
            'seated_at' => $this->seated_at,
            'cancelled_at' => $this->cancelled_at,
            'no_show_at' => $this->no_show_at,
            'table' => $this->table ? [
                'uuid' => $this->table->uuid,
                'label' => $this->table->label,
                'seats' => $this->table->seats,
            ] : null,
            'seated_comanda_uuid' => $this->seatedComanda?->uuid,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
