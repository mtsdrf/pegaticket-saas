<?php

namespace App\Services\Finance;

use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettlementGenerationService
{
    /**
     * @return array{groups_seen:int,settlements_created:int,receivables_linked:int}
     */
    public function generateAvailable(?CarbonInterface $cutoffAt = null, ?int $tenantId = null): array
    {
        $cutoffAt = ($cutoffAt ?? now())->copy();

        $groupRows = Receivable::query()
            ->selectRaw('tenant_id, event_id, date(available_at) as available_date')
            ->whereNull('deleted_at')
            ->whereNull('settlement_id')
            ->where('status', 'scheduled')
            ->where('available_at', '<=', $cutoffAt)
            ->where('net_amount', '>', 0)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->groupByRaw('tenant_id, event_id, date(available_at)')
            ->orderBy('tenant_id')
            ->get();

        $groupsSeen = 0;
        $settlementsCreated = 0;
        $receivablesLinked = 0;

        foreach ($groupRows as $groupRow) {
            $groupsSeen++;

            $result = $this->createSettlementForGroup(
                tenantId: (int) $groupRow->tenant_id,
                eventId: $groupRow->event_id !== null ? (int) $groupRow->event_id : null,
                availableDate: (string) $groupRow->available_date,
                cutoffAt: $cutoffAt,
            );

            if ($result === null) {
                continue;
            }

            $settlementsCreated++;
            $receivablesLinked += $result['receivables_count'];
        }

        return [
            'groups_seen' => $groupsSeen,
            'settlements_created' => $settlementsCreated,
            'receivables_linked' => $receivablesLinked,
        ];
    }

    /**
     * @return array{settlement:Settlement,receivables_count:int}|null
     */
    private function createSettlementForGroup(
        int $tenantId,
        ?int $eventId,
        string $availableDate,
        CarbonInterface $cutoffAt,
    ): ?array {
        return DB::transaction(function () use ($tenantId, $eventId, $availableDate, $cutoffAt) {
            $receivables = $this->queryReceivablesForGroup($tenantId, $eventId, $availableDate, $cutoffAt)
                ->lockForUpdate()
                ->get();

            if ($receivables->isEmpty()) {
                return null;
            }

            $settlement = Settlement::create([
                'tenant_id' => $tenantId,
                'code' => $this->generateSettlementCode($tenantId),
                'status' => 'scheduled',
                'scheduled_for' => $this->resolveScheduledFor($receivables),
                'released_at' => null,
                'gross_amount' => number_format((float) $receivables->sum('gross_amount'), 2, '.', ''),
                'platform_fee_amount' => number_format((float) $receivables->sum('platform_fee_amount'), 2, '.', ''),
                'processor_fee_amount' => number_format((float) $receivables->sum('processor_fee_amount'), 2, '.', ''),
                'net_amount' => number_format((float) $receivables->sum('net_amount'), 2, '.', ''),
                'metadata' => [
                    'event_id' => $eventId,
                    'available_window_date' => $availableDate,
                    'receivable_count' => $receivables->count(),
                    'provider_split_ids' => $receivables->pluck('provider_split_id')->filter()->unique()->values()->all(),
                ],
            ]);

            $receivableIds = $receivables->pluck('id')->all();

            Receivable::query()
                ->whereIn('id', $receivableIds)
                ->update([
                    'settlement_id' => $settlement->id,
                    'status' => 'awaiting_release',
                    'updated_at' => now(),
                ]);

            return [
                'settlement' => $settlement,
                'receivables_count' => count($receivableIds),
            ];
        });
    }

    private function queryReceivablesForGroup(
        int $tenantId,
        ?int $eventId,
        string $availableDate,
        CarbonInterface $cutoffAt,
    ) {
        return Receivable::query()
            ->whereNull('deleted_at')
            ->whereNull('settlement_id')
            ->where('status', 'scheduled')
            ->where('tenant_id', $tenantId)
            ->when(
                $eventId !== null,
                fn ($query) => $query->where('event_id', $eventId),
                fn ($query) => $query->whereNull('event_id'),
            )
            ->whereDate('available_at', $availableDate)
            ->where('available_at', '<=', $cutoffAt)
            ->where('net_amount', '>', 0)
            ->orderBy('id');
    }

    private function resolveScheduledFor(Collection $receivables): CarbonInterface
    {
        /** @var Receivable $lastReceivable */
        $lastReceivable = $receivables
            ->sortBy(fn (Receivable $receivable) => $receivable->available_at?->getTimestamp() ?? 0)
            ->last();

        return ($lastReceivable->available_at ?? now())->copy();
    }

    private function generateSettlementCode(int $tenantId): string
    {
        return 'SET-'.$tenantId.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
