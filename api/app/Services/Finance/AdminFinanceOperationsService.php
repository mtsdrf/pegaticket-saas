<?php

namespace App\Services\Finance;

use App\Models\Event\Event;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminFinanceOperationsService
{
    public function dashboard(array $filters = []): array
    {
        $tenantId = $this->resolveTenantId($filters['tenant_uuid'] ?? null);

        $receivablesBase = Receivable::query()
            ->whereNull('receivables.deleted_at')
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));

        $adjustmentsBase = SettlementAdjustment::query()
            ->whereNull('settlement_adjustments.deleted_at')
            ->whereIn('status', ['pending_recovery', 'pending_review'])
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));

        $settlementsBase = Settlement::query()
            ->whereNull('settlements.deleted_at')
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));

        $byTenant = (clone $receivablesBase)
            ->join('tenants', 'tenants.id', '=', 'receivables.tenant_id')
            ->selectRaw('tenants.uuid as tenant_uuid, tenants.name as tenant_name, COUNT(receivables.id) as total_count, SUM(receivables.net_amount) as total_amount')
            ->whereNull('tenants.deleted_at')
            ->groupBy('tenants.uuid', 'tenants.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $upcomingSettlement = (clone $settlementsBase)
            ->with('tenant')
            ->whereIn('status', ['scheduled', 'release_requested'])
            ->orderBy('scheduled_for')
            ->first();

        return [
            'balances' => [
                'available_now_amount' => round((float) (clone $receivablesBase)->where('available_at', '<=', now())->sum('net_amount'), 2),
                'future_amount' => round((float) (clone $receivablesBase)->where('available_at', '>', now())->sum('net_amount'), 2),
                'in_custody_amount' => round((float) (clone $receivablesBase)->whereIn('status', ['awaiting_release', 'release_requested'])->sum('net_amount'), 2),
                'released_amount' => round((float) (clone $receivablesBase)->where('status', 'released')->sum('net_amount'), 2),
                'open_adjustments_amount' => round((float) (clone $adjustmentsBase)->sum('amount'), 2),
                'risk_reserve_held_amount' => round((float) (clone $receivablesBase)->where('reserve_status', 'held')->sum('reserve_amount'), 2),
                'risk_reserve_available_to_release_amount' => round((float) (clone $receivablesBase)->where('reserve_status', 'held')->where('reserve_release_at', '<=', now())->sum('reserve_amount'), 2),
            ],
            'queues' => [
                'pending_receivables_count' => (clone $receivablesBase)->whereIn('status', ['scheduled', 'awaiting_release', 'release_requested'])->count(),
                'open_adjustments_count' => (clone $adjustmentsBase)->count(),
                'scheduled_settlements_count' => (clone $settlementsBase)->whereIn('status', ['scheduled', 'release_requested'])->count(),
            ],
            'upcoming_settlement' => $upcomingSettlement ? [
                'uuid' => $upcomingSettlement->uuid,
                'code' => $upcomingSettlement->code,
                'status' => $upcomingSettlement->status,
                'scheduled_for' => $upcomingSettlement->scheduled_for,
                'net_amount' => round((float) $upcomingSettlement->net_amount, 2),
                'tenant' => $upcomingSettlement->tenant ? [
                    'uuid' => $upcomingSettlement->tenant->uuid,
                    'name' => $upcomingSettlement->tenant->name,
                ] : null,
            ] : null,
            'top_tenants_by_receivables' => $byTenant->map(fn ($row) => [
                'tenant' => [
                    'uuid' => $row->tenant_uuid,
                    'name' => $row->tenant_name,
                ],
                'receivables_count' => (int) $row->total_count,
                'net_amount' => round((float) $row->total_amount, 2),
            ])->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function receivables(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = $this->resolveTenantId($filters['tenant_uuid'] ?? null);
        $eventId = $this->resolveEventId($tenantId, $filters['event_uuid'] ?? null);

        $query = Receivable::query()
            ->whereNull('receivables.deleted_at')
            ->with(['tenant', 'sale', 'event', 'settlement'])
            ->withSum(['adjustments as open_adjustments_amount' => function ($adjustments) {
                $adjustments->whereNull('deleted_at')->whereIn('status', ['pending_recovery', 'pending_review']);
            }], 'amount')
            ->orderByDesc('available_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($eventId !== null) {
            $query->where('event_id', $eventId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('available_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('available_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    public function settlements(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = $this->resolveTenantId($filters['tenant_uuid'] ?? null);

        $query = Settlement::query()
            ->whereNull('settlements.deleted_at')
            ->with(['tenant'])
            ->withCount(['receivables'])
            ->withSum(['adjustments as open_adjustments_amount' => function ($adjustments) {
                $adjustments->whereNull('deleted_at')->whereIn('status', ['pending_recovery', 'pending_review']);
            }], 'amount')
            ->orderByDesc('scheduled_for');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('scheduled_for', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('scheduled_for', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    public function adjustments(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = $this->resolveTenantId($filters['tenant_uuid'] ?? null);

        $query = SettlementAdjustment::query()
            ->whereNull('settlement_adjustments.deleted_at')
            ->with(['tenant', 'sale', 'receivable', 'settlement'])
            ->orderByDesc('id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    private function resolveTenantId(?string $tenantUuid): ?int
    {
        if ($tenantUuid === null) {
            return null;
        }

        $tenant = Tenant::query()
            ->where('uuid', $tenantUuid)
            ->whereNull('tenants.deleted_at')
            ->first();

        if ($tenant === null) {
            abort(404);
        }

        return $tenant->id;
    }

    private function resolveEventId(?int $tenantId, ?string $eventUuid): ?int
    {
        if ($eventUuid === null) {
            return null;
        }

        $event = Event::query()
            ->where('uuid', $eventUuid)
            ->whereNull('events.deleted_at')
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->first();

        if ($event === null) {
            abort(404);
        }

        return $event->id;
    }
}
