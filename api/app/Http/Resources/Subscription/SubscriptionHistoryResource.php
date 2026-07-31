<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item do histórico completo de assinaturas do tenant (`GET
 * /subscription/history`) — todas as linhas de `subscriptions` ao longo do
 * tempo, não só a atual. Deliberadamente mais enxuto que SubscriptionResource
 * (sem faturas/checkout_url): a lista é para visão geral, não para operar a
 * assinatura atual.
 */
class SubscriptionHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'billing_period' => $this->billing_period,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan === null ? null : [
                'uuid' => $this->plan->uuid,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
            ]),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'cancel_at' => $this->cancel_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'auto_renew' => (bool) $this->auto_renew,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
