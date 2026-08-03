<?php

namespace App\Services\Storefront;

use App\Models\Storefront\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Envio de Web Push real (VAPID) pro cliente final (roadmap Delivery, Fase
 * 4 — última fatia). `WebPush` é injetado via container (singleton
 * registrado em AppServiceProvider, construído a partir de
 * config('services.vapid')) — nunca instanciado direto aqui, para permitir
 * mock/fake em teste sem bater no provedor de push real.
 *
 * Crítico: envio de push NUNCA pode quebrar o fluxo principal da venda —
 * qualquer falha (exceção, timeout, subscription expirada) é capturada e
 * logada, nunca propagada.
 */
class PushNotificationService
{
    public function __construct(private WebPush $webPush)
    {
    }

    public function notifyFinalCustomer(int $finalCustomerId, string $title, string $body, string $url): void
    {
        $subscriptions = PushSubscription::where('final_customer_id', $finalCustomerId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);

        foreach ($subscriptions as $subscription) {
            $this->sendToSubscription($subscription, $payload);
        }
    }

    private function sendToSubscription(PushSubscription $subscription, string $payload): void
    {
        try {
            $report = $this->webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->p256dh,
                        'auth' => $subscription->auth,
                    ],
                ]),
                $payload
            );

            if ($report->isSubscriptionExpired()) {
                $subscription->delete();
            }
        } catch (Throwable $e) {
            Log::warning('Falha ao enviar Web Push notification', [
                'push_subscription_id' => $subscription->id,
                'final_customer_id' => $subscription->final_customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
