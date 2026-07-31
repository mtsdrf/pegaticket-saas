<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wrapper de saída para `SubscriptionService::getPlanPricing()` — recebe o
 * array já montado pelo Service (plano atual do tenant, preço real por
 * período e funcionalidades incluídas), não um Eloquent Model.
 */
class PlanPricingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'plan' => $this->resource['plan'],
            'billing_periods' => $this->resource['billing_periods'],
            'functionalities' => $this->resource['functionalities'],
        ];
    }
}
