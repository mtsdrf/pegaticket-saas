<?php

namespace App\Http\Controllers\Webhook;

use App\DTOs\Webhook\CreateWebhookSubscriptionDTO;
use App\DTOs\Webhook\UpdateWebhookSubscriptionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\StoreWebhookSubscriptionRequest;
use App\Http\Requests\Webhook\UpdateWebhookSubscriptionRequest;
use App\Http\Resources\Webhook\WebhookDeliveryResource;
use App\Http\Resources\Webhook\WebhookSubscriptionResource;
use App\Models\Webhook\WebhookSubscription;
use App\Services\APIResponse;
use App\Services\Webhook\WebhookSubscriptionService;

class WebhookSubscriptionController extends Controller
{
    public function __construct(private WebhookSubscriptionService $service)
    {
    }

    public function index()
    {
        $subscriptions = $this->service->listForTenant(app('tenant_id'));

        return APIResponse::success(
            WebhookSubscriptionResource::collection($subscriptions),
            __('messages.webhook_subscription.list')
        );
    }

    /**
     * Única resposta que carrega `secret` em texto puro — usado pelo
     * cliente externo pra validar a assinatura HMAC de cada delivery.
     * Nunca mais retornado depois (WebhookSubscriptionResource nunca expõe
     * o campo).
     */
    public function store(StoreWebhookSubscriptionRequest $request)
    {
        $dto = CreateWebhookSubscriptionDTO::fromArray($request->validated());

        $subscription = $this->service->create(app('tenant_id'), $dto);

        return APIResponse::success(
            array_merge(
                (new WebhookSubscriptionResource($subscription))->resolve(),
                ['secret' => $subscription->secret]
            ),
            __('messages.webhook_subscription.created'),
            201
        );
    }

    public function show(WebhookSubscription $webhookSubscription)
    {
        $subscription = $this->service->find($webhookSubscription);

        return APIResponse::success(
            new WebhookSubscriptionResource($subscription),
            __('messages.webhook_subscription.show')
        );
    }

    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription)
    {
        $dto = UpdateWebhookSubscriptionDTO::fromArray($request->validated());

        $subscription = $this->service->update($webhookSubscription, $dto);

        return APIResponse::success(
            new WebhookSubscriptionResource($subscription),
            __('messages.webhook_subscription.updated')
        );
    }

    public function destroy(WebhookSubscription $webhookSubscription)
    {
        $this->service->delete($webhookSubscription);

        return APIResponse::success(
            null,
            __('messages.webhook_subscription.deleted'),
            204
        );
    }

    /**
     * Histórico mínimo de entrega (debug/central de chamados futuro).
     */
    public function deliveries(WebhookSubscription $webhookSubscription)
    {
        $subscription = $this->service->find($webhookSubscription);

        $deliveries = $subscription->deliveries()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return APIResponse::success(
            WebhookDeliveryResource::collection($deliveries),
            __('messages.webhook_subscription.deliveries')
        );
    }
}
