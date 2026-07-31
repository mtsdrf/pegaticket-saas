<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata `App\Support\Payment\PaymentIssueEntry` (não é um Model Eloquent
 * — item normalizado de 4 fontes heterogêneas, ver PaymentIssueService).
 */
class PaymentIssueResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource->toArray();
    }
}
