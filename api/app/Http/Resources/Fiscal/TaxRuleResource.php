<?php

namespace App\Http\Resources\Fiscal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'tax_type' => $this->tax_type,
            'rate_percent' => (float) $this->rate_percent,
            'scope' => $this->scope,
            'valid_from' => optional($this->valid_from)?->toDateString(),
            'valid_to' => optional($this->valid_to)?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
