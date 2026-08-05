<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Confirmação mínima de escrita — endpoint write-only, sem tela de leitura pública. */
class FunnelEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'step' => $this->step,
            'created_at' => $this->created_at,
        ];
    }
}
