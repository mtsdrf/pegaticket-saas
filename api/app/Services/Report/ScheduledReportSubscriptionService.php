<?php

namespace App\Services\Report;

use App\DTOs\Report\CreateScheduledReportSubscriptionDTO;
use App\Events\Report\ScheduledReportSubscriptionCancelled;
use App\Events\Report\ScheduledReportSubscriptionCreated;
use App\Models\Report\ScheduledReportSubscription;
use App\Repositories\Contracts\ScheduledReportSubscriptionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD simples de assinatura de resumo agendado (roadmap A2) — criar,
 * listar, cancelar. Disparo diário fica em
 * App\Console\Commands\SendScheduledReportSummariesCommand.
 */
class ScheduledReportSubscriptionService
{
    public function __construct(
        private ScheduledReportSubscriptionRepositoryInterface $repository
    ) {}

    /** @return Collection<int, ScheduledReportSubscription> */
    public function listForTenant(int $tenantId): Collection
    {
        return $this->repository->allForTenant($tenantId);
    }

    public function create(CreateScheduledReportSubscriptionDTO $dto): ScheduledReportSubscription
    {
        return DB::transaction(function () use ($dto) {
            $subscription = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'recipient_email' => $dto->recipientEmail,
                'frequency' => $dto->frequency,
            ]);

            event(new ScheduledReportSubscriptionCreated(
                subscriptionUuid: $subscription->uuid,
                tenantId: $dto->tenantId,
                recipientEmail: $dto->recipientEmail,
                actorId: (int) Auth::id()
            ));

            return $subscription;
        });
    }

    public function cancel(int $tenantId, string $uuid): void
    {
        $subscription = $this->repository->findForTenantOrFail($tenantId, $uuid);

        DB::transaction(function () use ($subscription, $tenantId) {
            $this->repository->delete($subscription);

            event(new ScheduledReportSubscriptionCancelled(
                subscriptionUuid: $subscription->uuid,
                tenantId: $tenantId,
                actorId: (int) Auth::id()
            ));
        });
    }
}
