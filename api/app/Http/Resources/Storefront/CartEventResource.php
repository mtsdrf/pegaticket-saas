<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Confirmação mínima de escrita — endpoint write-only, sem tela de leitura
 * ainda (roadmap A3.14).
 */
class CartEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'event_type' => $this->event_type,
            'created_at' => $this->created_at,
        ];
    }
}
