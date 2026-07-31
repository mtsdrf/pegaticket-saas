<?php

namespace App\Http\Resources\Webhook;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nunca expõe `secret` — o tenant precisa dele só pra validar a assinatura
 * HMAC de cada delivery recebida, valor fixo desde a criação (não há rota
 * de "mostrar o secret de novo"; se perder, recriar a subscription).
 */
class WebhookSubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url,
            'event_types' => $this->event_types,
            'is_active' => $this->is_active,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
