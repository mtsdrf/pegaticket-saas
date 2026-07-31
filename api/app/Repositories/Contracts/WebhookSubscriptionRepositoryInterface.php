<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface WebhookSubscriptionRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;

    public function activeForTenantAndEvent(int $tenantId, string $eventType): Collection;
}
