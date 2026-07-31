<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'billing_period' => $this->billing_period,
            'plan' => $this->whenLoaded('plan', fn () => [
                'uuid' => $this->plan->uuid,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
            ]),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'is_trial' => $this->status === 'trialing',
            'trial_days_remaining' => ($this->trial_ends_at !== null && $this->trial_ends_at->isFuture())
                ? (int) ceil(now()->diffInHours($this->trial_ends_at) / 24)
                : null,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'next_charge_at' => $this->next_charge_at?->toIso8601String(),
            'cancel_at' => $this->cancel_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
            'auto_renew' => (bool) $this->auto_renew,
            'accepted_terms_version' => $this->accepted_terms_version,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'checkout_url' => $this->when(
                $this->resource->getAttribute('checkout_url') !== null,
                fn () => $this->resource->getAttribute('checkout_url')
            ),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
