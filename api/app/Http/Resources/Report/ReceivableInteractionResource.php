<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableInteractionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'interaction_type' => $this->interaction_type,
            'interaction_type_label' => match ($this->interaction_type) {
                'whatsapp' => 'WhatsApp',
                'promise' => 'Promessa',
                default => 'Anotação',
            },
            'channel' => $this->channel,
            'notes' => $this->notes,
            'promised_amount' => $this->promised_amount,
            'promised_due_date' => $this->promised_due_date,
            'contacted_at' => $this->contacted_at,
            'created_by_name' => $this->createdByUser?->name,
            'installment_uuid' => $this->installment?->uuid,
            'created_at' => $this->created_at,
        ];
    }
}
