<?php

namespace App\Services\Webhook;

use App\DTOs\Webhook\CreateWebhookSubscriptionDTO;
use App\DTOs\Webhook\UpdateWebhookSubscriptionDTO;
use App\Events\Webhook\WebhookSubscriptionCreated;
use App\Events\Webhook\WebhookSubscriptionDeleted;
use App\Events\Webhook\WebhookSubscriptionUpdated;
use App\Models\Webhook\WebhookSubscription;
use App\Repositories\Contracts\WebhookSubscriptionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WebhookSubscriptionService
{
    public function __construct(
        private WebhookSubscriptionRepositoryInterface $repository,
    ) {
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function find(WebhookSubscription $subscription): WebhookSubscription
    {
        $this->assertBelongsToCurrentTenant($subscription);

        return $subscription;
    }

    public function create(int $tenantId, CreateWebhookSubscriptionDTO $dto): WebhookSubscription
    {
        $subscription = $this->repository->create([
            'tenant_id' => $tenantId,
            'url' => $dto->url,
            'event_types' => $dto->eventTypes,
            'secret' => Str::random(64),
            'is_active' => $dto->isActive,
        ]);

        event(new WebhookSubscriptionCreated(
            subscriptionUuid: $subscription->uuid,
            tenantId: $tenantId,
            actorId: (int) Auth::id()
        ));

        return $subscription;
    }

    public function update(WebhookSubscription $subscription, UpdateWebhookSubscriptionDTO $dto): WebhookSubscription
    {
        $this->assertBelongsToCurrentTenant($subscription);

        $subscription = $this->repository->update($subscription, [
            'url' => $dto->url,
            'event_types' => $dto->eventTypes,
            'is_active' => $dto->isActive,
        ]);

        event(new WebhookSubscriptionUpdated(
            subscriptionUuid: $subscription->uuid,
            tenantId: $subscription->tenant_id,
            actorId: (int) Auth::id()
        ));

        return $subscription;
    }

    public function delete(WebhookSubscription $subscription): void
    {
        $this->assertBelongsToCurrentTenant($subscription);

        $tenantId = $subscription->tenant_id;
        $uuid = $subscription->uuid;

        $this->repository->delete($subscription);

        event(new WebhookSubscriptionDeleted(
            subscriptionUuid: $uuid,
            tenantId: $tenantId,
            actorId: (int) Auth::id()
        ));
    }

    private function assertBelongsToCurrentTenant(WebhookSubscription $subscription): void
    {
        if ((int) $subscription->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
