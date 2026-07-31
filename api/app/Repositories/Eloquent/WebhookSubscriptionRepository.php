<?php

namespace App\Repositories\Eloquent;

use App\Models\Webhook\WebhookSubscription;
use App\Repositories\Contracts\WebhookSubscriptionRepositoryInterface;
use Illuminate\Support\Collection;

class WebhookSubscriptionRepository extends BaseRepository implements WebhookSubscriptionRepositoryInterface
{
    public function __construct(WebhookSubscription $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Filtro de `event_types` (JSON) é feito em PHP após a query, não via
     * whereJsonContains: o volume esperado por tenant é pequeno (poucas
     * subscriptions cadastradas manualmente) e evita depender de suporte a
     * JSON do driver de banco em teste (SQLite) vs produção (MySQL).
     */
    public function activeForTenantAndEvent(int $tenantId, string $eventType): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookSubscription $subscription) => $subscription->subscribesTo($eventType))
            ->values();
    }
}
