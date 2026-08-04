<?php

namespace App\Services\Finance;

use App\Models\Event\Event;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class FinanceOperationsService
{
    public function dashboard(int $tenantId, array $filters = []): array
    {
        $eventId = $this->resolveEventId($tenantId, $filters['event_uuid'] ?? null);

        $receivablesBase = Receivable::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($eventId !== null, fn (Builder $query) => $query->where('event_id', $eventId));

        $adjustmentsBase = SettlementAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending_recovery', 'pending_review']);

        if ($eventId !== null) {
            $adjustmentsBase->whereIn('receivable_id', function ($sub) use ($tenantId, $eventId) {
                $sub->select('id')
                    ->from('receivables')
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at');
            });
        }

        $inCustodyAmount = (clone $receivablesBase)
            ->whereIn('status', ['awaiting_release', 'release_requested'])
            ->sum('net_amount');

        $releasedAmount = (clone $receivablesBase)
            ->where('status', 'released')
            ->sum('net_amount');

        $futureAmount = (clone $receivablesBase)
            ->where('available_at', '>', now())
            ->sum('net_amount');

        $availableNowAmount = (clone $receivablesBase)
            ->where('available_at', '<=', now())
            ->sum('net_amount');

        $pendingReceivableCount = (clone $receivablesBase)
            ->whereIn('status', ['scheduled', 'awaiting_release', 'release_requested'])
            ->count();

        $riskReserveHeldAmount = (clone $receivablesBase)
            ->where('reserve_status', 'held')
            ->sum('reserve_amount');

        $riskReserveAvailableToReleaseAmount = (clone $receivablesBase)
            ->where('reserve_status', 'held')
            ->where('reserve_release_at', '<=', now())
            ->sum('reserve_amount');

        $upcomingSettlement = Settlement::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['scheduled', 'release_requested'])
            ->orderBy('scheduled_for')
            ->first();

        $statusBreakdown = (clone $receivablesBase)
            ->selectRaw('status, COUNT(*) as total_count, SUM(net_amount) as total_amount')
            ->groupBy('status')
            ->get();

        return [
            'balances' => [
                'available_now_amount' => round((float) $availableNowAmount, 2),
                'future_amount' => round((float) $futureAmount, 2),
                'in_custody_amount' => round((float) $inCustodyAmount, 2),
                'released_amount' => round((float) $releasedAmount, 2),
                'open_adjustments_amount' => round((float) (clone $adjustmentsBase)->sum('amount'), 2),
                'risk_reserve_held_amount' => round((float) $riskReserveHeldAmount, 2),
                'risk_reserve_available_to_release_amount' => round((float) $riskReserveAvailableToReleaseAmount, 2),
            ],
            'queues' => [
                'pending_receivables_count' => $pendingReceivableCount,
                'released_settlements_count' => Settlement::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', 'released')
                    ->count(),
                'open_adjustments_count' => (clone $adjustmentsBase)->count(),
            ],
            'upcoming_settlement' => $upcomingSettlement ? [
                'uuid' => $upcomingSettlement->uuid,
                'code' => $upcomingSettlement->code,
                'status' => $upcomingSettlement->status,
                'scheduled_for' => $upcomingSettlement->scheduled_for,
                'net_amount' => round((float) $upcomingSettlement->net_amount, 2),
            ] : null,
            'receivables_by_status' => $statusBreakdown->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->total_count,
                'amount' => round((float) $row->total_amount, 2),
            ])->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function receivables(int $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $eventId = $this->resolveEventId($tenantId, $filters['event_uuid'] ?? null);
        $settlementId = $this->resolveSettlementId($tenantId, $filters['settlement_uuid'] ?? null);

        $query = Receivable::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['sale', 'event', 'settlement'])
            ->withSum(['adjustments as open_adjustments_amount' => function ($adjustments) {
                $adjustments->whereNull('deleted_at')->whereIn('status', ['pending_recovery', 'pending_review']);
            }], 'amount')
            ->orderBy('available_at');

        if ($eventId !== null) {
            $query->where('event_id', $eventId);
        }

        if ($settlementId !== null) {
            $query->where('settlement_id', $settlementId);
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

    public function receivablesSummary(int $tenantId, array $filters): array
    {
        $eventId = $this->resolveEventId($tenantId, $filters['event_uuid'] ?? null);
        $settlementId = $this->resolveSettlementId($tenantId, $filters['settlement_uuid'] ?? null);

        $base = Receivable::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($eventId !== null) {
            $base->where('event_id', $eventId);
        }

        if ($settlementId !== null) {
            $base->where('settlement_id', $settlementId);
        }

        if (! empty($filters['status'])) {
            $base->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $base->whereDate('available_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $base->whereDate('available_at', '<=', $filters['to']);
        }

        $rows = (clone $base)
            ->selectRaw('status, COUNT(*) as total_count, SUM(net_amount) as total_amount')
            ->groupBy('status')
            ->get();

        return [
            'total_count' => (clone $base)->count(),
            'total_net_amount' => round((float) (clone $base)->sum('net_amount'), 2),
            'by_status' => $rows->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->total_count,
                'amount' => round((float) $row->total_amount, 2),
            ])->values()->all(),
        ];
    }

    public function settlements(int $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Settlement::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->withCount(['receivables'])
            ->withSum(['adjustments as open_adjustments_amount' => function ($adjustments) {
                $adjustments->whereNull('deleted_at')->whereIn('status', ['pending_recovery', 'pending_review']);
            }], 'amount')
            ->orderByDesc('scheduled_for');

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

    public function settlementsSummary(int $tenantId, array $filters): array
    {
        $base = Settlement::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (! empty($filters['status'])) {
            $base->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $base->whereDate('scheduled_for', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $base->whereDate('scheduled_for', '<=', $filters['to']);
        }

        $rows = (clone $base)
            ->selectRaw('status, COUNT(*) as total_count, SUM(net_amount) as total_amount')
            ->groupBy('status')
            ->get();

        return [
            'total_count' => (clone $base)->count(),
            'total_net_amount' => round((float) (clone $base)->sum('net_amount'), 2),
            'by_status' => $rows->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->total_count,
                'amount' => round((float) $row->total_amount, 2),
            ])->values()->all(),
        ];
    }

    private function resolveEventId(int $tenantId, ?string $eventUuid): ?int
    {
        if ($eventUuid === null) {
            return null;
        }

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $eventUuid)
            ->whereNull('deleted_at')
            ->first();

        if ($event === null) {
            abort(404);
        }

        return $event->id;
    }

    private function resolveSettlementId(int $tenantId, ?string $settlementUuid): ?int
    {
        if ($settlementUuid === null) {
            return null;
        }

        $settlement = Settlement::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $settlementUuid)
            ->whereNull('deleted_at')
            ->first();

        if ($settlement === null) {
            abort(404);
        }

        return $settlement->id;
    }
}
