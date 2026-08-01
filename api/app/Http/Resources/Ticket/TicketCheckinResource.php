<?php

namespace App\Http\Resources\Ticket;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketCheckinResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'gate_name' => $this->gate_name,
            'result' => $this->result,
            'checked_in_at' => $this->checked_in_at,
            'device_info' => $this->device_info,
            'operator' => $this->whenLoaded('operator', fn() => $this->operator ? [
                'uuid' => $this->operator->uuid,
                'name' => $this->operator->name,
            ] : null),
        ];
    }
}
