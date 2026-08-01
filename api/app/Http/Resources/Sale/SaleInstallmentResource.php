<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mesmo shape do array montado inline em SaleResource::toArray()
 * ->installments[] — mantido idêntico de propósito para o frontend não
 * lidar com 2 formatos diferentes pro mesmo tipo de dado.
 */
class SaleInstallmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'installment_number' => $this->installment_number,
            'amount' => $this->amount,
            'due_date' => $this->due_date,
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at,
        ];
    }
}
