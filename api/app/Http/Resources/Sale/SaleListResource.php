<?php

namespace App\Http\Resources\Sale;

use App\Models\Sale\Sale;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'is_installment' => $this->is_installment,
            'total_amount' => $this->total_amount,
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at,
            'due_date' => $this->due_date,
            'cancelled_at' => $this->cancelled_at,
            'status' => $this->status,
            'origin' => Sale::normalizeOrigin($this->origin),
            'final_customer' => $this->whenLoaded('finalCustomer', fn() => [
                'uuid' => $this->finalCustomer->uuid,
                'name' => $this->finalCustomer->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
