<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class ReactivationRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'days_without_order' => $this->days_without_order,
            'coupon_type' => $this->coupon_type,
            'coupon_value' => $this->coupon_value,
            'coupon_validity_days' => $this->coupon_validity_days,
            'is_active' => $this->is_active,
        ];
    }
}
