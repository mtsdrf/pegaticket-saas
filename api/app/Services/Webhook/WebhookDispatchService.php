<?php

namespace App\Services\Webhook;

use App\Http\Resources\Order\OrderResource;
use App\Jobs\Webhook\SendWebhookJob;
use App\Models\Order\Order;
use App\Repositories\Contracts\WebhookSubscriptionRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Lado "de saída" dos webhooks (roadmap A6, item 20). Chamado sincronamente
 * pelos listeners de auditoria de domínio (ex: DispatchWebhookOnOrderCreated)
 * — só faz a query de subscriptions + monta o payload (rápido); o POST HTTP
 * em si acontece de forma assíncrona no SendWebhookJob (fila `webhooks`),
 * pra não bloquear a request principal que disparou o evento.
 */
class WebhookDispatchService
{
    public function __construct(
        private WebhookSubscriptionRepositoryInterface $repository,
    ) {
    }

    public function dispatchForOrder(string $eventType, Order $order): void
    {
        $subscriptions = $this->repository->activeForTenantAndEvent($order->tenant_id, $eventType);

        if ($subscriptions->isEmpty()) {
            return;
        }

        $order->loadMissing([
            'client.endereco.cidade',
            'client.endereco.bairro',
            'stockLocation',
            'items.product',
            'installments',
            'coupon',
            'rating',
        ]);

        $payload = (new OrderResource($order))->resolve();

        foreach ($subscriptions as $subscription) {
            SendWebhookJob::dispatch(
                $subscription->id,
                $eventType,
                $payload,
                (string) Str::uuid()
            )->onQueue('webhooks');
        }
    }
}
