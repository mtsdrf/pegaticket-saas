<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'is_installment' => $this->is_installment,
            'total_amount' => $this->total_amount,
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at,
            'is_delivered' => $this->is_delivered,
            'delivered_at' => $this->delivered_at,
            'due_date' => $this->due_date,
            'cancelled_at' => $this->cancelled_at,
            'status' => $this->status,
            'origin' => $this->origin,
            'is_out_for_delivery' => $this->is_out_for_delivery,
            'out_for_delivery_at' => $this->out_for_delivery_at,
            'client' => $this->whenLoaded('client', fn() => [
                'uuid' => $this->client->uuid,
                'name' => $this->client->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
