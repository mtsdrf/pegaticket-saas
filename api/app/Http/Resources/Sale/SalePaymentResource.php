<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Saída de uma cobrança de pagamento de venda (roadmap 2A). Expõe só o que
 * o frontend precisa pra montar a tela de pagamento; nunca vaza payable_id
 * interno (já oculto no model Payment).
 */
class SalePaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'provider_charge_id' => $this->provider_charge_id,
            'method' => $this->method,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'metadata' => $this->metadata,
        ];
    }
}
