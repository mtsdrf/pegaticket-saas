<?php

namespace App\Services\Subscription;

use App\Enums\Subscription\SubscriptionStatus;
use App\Exceptions\Subscription\InvalidSubscriptionTransitionException;
use App\Models\Subscription\Subscription;
use App\Models\Subscription\SubscriptionEvent;
use Illuminate\Support\Facades\DB;

/**
 * Máquina de estados da assinatura (roadmap 1B). Fonte única de verdade das
 * transições permitidas. transition() valida, persiste o novo status e
 * grava uma linha imutável em subscription_events, tudo atômico.
 */
class SubscriptionStateMachine
{
    /**
     * Mapa de transições permitidas: de => [para, ...].
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['trialing', 'active', 'canceled'],
        'trialing' => ['active', 'past_due', 'cancel_scheduled', 'canceled'],
        'active' => ['past_due', 'suspended', 'cancel_scheduled', 'canceled'],
        'past_due' => ['active', 'suspended', 'canceled'],
        'suspended' => ['active', 'canceled'],
        'cancel_scheduled' => ['active', 'canceled'],
        'canceled' => [], // terminal
    ];

    public function canTransition(SubscriptionStatus $from, SubscriptionStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Aplica a transição. Lança InvalidSubscriptionTransitionException se
     * inválida; senão persiste o status e grava o subscription_event.
     *
     * @param array<string, mixed> $payload metadados extras do evento
     */
    public function transition(Subscription $subscription, SubscriptionStatus $to, array $payload = []): void
    {
        $from = SubscriptionStatus::from($subscription->status);

        if (! $this->canTransition($from, $to)) {
            throw new InvalidSubscriptionTransitionException(
                __('messages.subscription.invalid_transition')
            );
        }

        DB::transaction(function () use ($subscription, $from, $to, $payload) {
            $subscription->status = $to->value;
            $subscription->save();

            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'type' => $to->value,
                'payload' => array_merge(['from' => $from->value, 'to' => $to->value], $payload),
                'created_at' => now(),
            ]);
        });
    }
}
