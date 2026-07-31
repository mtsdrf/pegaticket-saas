<?php

namespace App\Http\Resources\Balcao;

use Illuminate\Http\Resources\Json\JsonResource;

class TableWaitlistResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'party_size' => $this->party_size,
            'quoted_wait_minutes' => $this->quoted_wait_minutes,
            'status' => $this->status,
            'notes' => $this->notes,
            'cancelled_reason' => $this->cancelled_reason,
            'called_at' => $this->called_at,
            'seated_at' => $this->seated_at,
            'cancelled_at' => $this->cancelled_at,
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
