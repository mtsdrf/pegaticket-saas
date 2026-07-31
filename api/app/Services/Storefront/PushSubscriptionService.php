<?php

namespace App\Services\Storefront;

use App\Models\Storefront\PushSubscription;
use Illuminate\Database\QueryException;

/**
 * Inscrição de Web Push do cliente final (roadmap Delivery, Fase 4 — Web
 * Push real). Sem Repository dedicado — mesma decisão já usada para
 * ProductFavorite/OrderRating: tabela auxiliar simples, sem
 * BaseModel/soft delete, Service acessa o Model direto.
 */
class PushSubscriptionService
{
    /**
     * Upsert por endpoint: o endpoint é único por navegador/dispositivo,
     * não por cliente — se o mesmo endpoint já estava associado a outro
     * cliente final (ou ao mesmo, com chaves diferentes), atualiza em vez
     * de duplicar.
     */
    public function subscribe(int $finalCustomerId, string $endpoint, string $p256dh, string $auth): PushSubscription
    {
        $subscription = PushSubscription::where('endpoint', $endpoint)->first();

        if ($subscription) {
            $subscription->update([
                'final_customer_id' => $finalCustomerId,
                'p256dh' => $p256dh,
                'auth' => $auth,
            ]);

            return $subscription->fresh();
        }

        // Duas chamadas concorrentes com o mesmo endpoint (dois cliques em
        // "permitir notificações") podem ambas passar pelo `first()` acima
        // sem achar nada — a unique em `endpoint` protege o dado, mas sem
        // esse catch a segunda vira 500 cru em vez de reusar a linha criada
        // pela primeira (mesmo padrão de ProductFavoriteService::toggle()).
        try {
            return PushSubscription::create([
                'final_customer_id' => $finalCustomerId,
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $auth,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            $subscription = PushSubscription::where('endpoint', $endpoint)->firstOrFail();
            $subscription->update([
                'final_customer_id' => $finalCustomerId,
                'p256dh' => $p256dh,
                'auth' => $auth,
            ]);

            return $subscription->fresh();
        }
    }
}
