<?php

namespace App\Services\Finance;

use App\Models\Event\Event;
use App\Models\Finance\Receivable;
use App\Models\Finance\SettlementAdjustment;
use App\Services\Event\EventService;
use Illuminate\Support\Collection;

class EventFinancialCloseoutService
{
    public function __construct(
        private EventService $eventService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $tenantId, Event $event): array
    {
        $event = $this->eventService->find($event);
        $receivables = $this->loadReceivables($tenantId, $event);
        $adjustments = $this->loadAdjustments($tenantId, $receivables);

        $grossAmount = round((float) $receivables->sum('gross_amount'), 2);
        $platformFeeAmount = round((float) $receivables->sum('platform_fee_amount'), 2);
        $processorFeeAmount = round((float) $receivables->sum('processor_fee_amount'), 2);
        $organizerNetAmount = round((float) $receivables->sum('net_amount'), 2);

        $releasedAmount = round((float) $receivables
            ->where('status', 'released')
            ->sum('net_amount'), 2);
        $inCustodyAmount = round((float) $receivables
            ->whereIn('status', ['awaiting_release', 'release_requested'])
            ->sum('net_amount'), 2);
        $futureAmount = round((float) $receivables
            ->filter(fn (Receivable $receivable) => $receivable->available_at !== null && $receivable->available_at->isFuture())
            ->sum('net_amount'), 2);

        $refundAmount = round((float) $adjustments
            ->filter(fn (SettlementAdjustment $adjustment) => str_starts_with($adjustment->type, 'refund_'))
            ->sum('amount'), 2);
        $chargebackAmount = round((float) $adjustments
            ->filter(fn (SettlementAdjustment $adjustment) => str_starts_with($adjustment->type, 'chargeback_'))
            ->sum('amount'), 2);
        $pendingRecoveryAmount = round((float) $adjustments
            ->where('status', 'pending_recovery')
            ->sum('amount'), 2);
        $pendingReviewAmount = round((float) $adjustments
            ->where('status', 'pending_review')
            ->sum('amount'), 2);
        $riskReserveHeldAmount = round((float) $receivables
            ->where('reserve_status', 'held')
            ->sum('reserve_amount'), 2);
        $riskReserveReleasedAmount = round((float) $receivables
            ->where('reserve_status', 'released')
            ->sum('reserve_amount'), 2);
        $manualNetEffect = round((float) $adjustments
            ->filter(fn (SettlementAdjustment $adjustment) => in_array($adjustment->type, ['manual_credit', 'manual_debit'], true))
            ->sum(fn (SettlementAdjustment $adjustment) => $adjustment->type === 'manual_credit'
                ? (float) $adjustment->amount
                : ((float) $adjustment->amount * -1)), 2);

        $settlementStatuses = $receivables
            ->map(fn (Receivable $receivable) => $receivable->settlement?->status)
            ->filter()
            ->countBy();

        $receivableStatuses = $receivables->countBy('status');
        $allReleased = $receivables->isNotEmpty() && $receivables->every(fn (Receivable $receivable) => $receivable->status === 'released');
        $hasAnySettlement = $receivables->contains(fn (Receivable $receivable) => $receivable->settlement !== null);
        $allAvailable = $receivables->isNotEmpty() && $receivables->every(
            fn (Receivable $receivable) => $receivable->available_at !== null && ! $receivable->available_at->isFuture()
        );
        $hasOpenExceptions = $pendingRecoveryAmount > 0 || $pendingReviewAmount > 0;

        $closeoutStatus = 'open';
        if ($allReleased && $hasOpenExceptions) {
            $closeoutStatus = 'settled_with_exceptions';
        } elseif ($allReleased) {
            $closeoutStatus = 'settled';
        } elseif ($hasAnySettlement) {
            $closeoutStatus = 'settling';
        } elseif ($allAvailable && $event->ends_at !== null && ! $event->ends_at->isFuture()) {
            $closeoutStatus = 'ready_to_settle';
        }

        return [
            'event' => [
                'uuid' => $event->uuid,
                'name' => $event->name,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'status' => $event->status,
            ],
            'closeout_status' => $closeoutStatus,
            'totals' => [
                'gross_amount' => $grossAmount,
                'platform_fee_amount' => $platformFeeAmount,
                'processor_fee_amount' => $processorFeeAmount,
                'organizer_net_amount' => $organizerNetAmount,
                'released_amount' => $releasedAmount,
                'in_custody_amount' => $inCustodyAmount,
                'future_amount' => $futureAmount,
                'refund_amount' => $refundAmount,
                'chargeback_amount' => $chargebackAmount,
                'manual_adjustments_net_effect' => $manualNetEffect,
                'pending_recovery_amount' => $pendingRecoveryAmount,
                'pending_review_amount' => $pendingReviewAmount,
                'risk_reserve_held_amount' => $riskReserveHeldAmount,
                'risk_reserve_released_amount' => $riskReserveReleasedAmount,
            ],
            'settlements' => [
                'count' => $receivables->pluck('settlement_id')->filter()->unique()->count(),
                'by_status' => $settlementStatuses->map(
                    fn (int $count, string $status) => ['status' => $status, 'count' => $count]
                )->values()->all(),
            ],
            'adjustments' => [
                'count' => $adjustments->count(),
                'open_count' => $adjustments->whereIn('status', ['pending_recovery', 'pending_review'])->count(),
                'by_status' => $adjustments->countBy('status')->map(
                    fn (int $count, string $status) => ['status' => $status, 'count' => $count]
                )->values()->all(),
            ],
            'receivables' => [
                'count' => $receivables->count(),
                'by_status' => $receivableStatuses->map(
                    fn (int $count, string $status) => ['status' => $status, 'count' => $count]
                )->values()->all(),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{content:string,filename:string}
     */
    public function exportCsv(int $tenantId, Event $event): array
    {
        $event = $this->eventService->find($event);
        $receivables = $this->loadReceivables($tenantId, $event);

        $handle = fopen('php://temp', 'r+');
        $header = [
            'event_uuid',
            'event_name',
            'sale_uuid',
            'sale_codigo',
            'payment_uuid',
            'payment_status',
            'receivable_uuid',
            'receivable_status',
            'provider',
            'provider_charge_id',
            'provider_split_id',
            'gross_amount',
            'platform_fee_amount',
            'processor_fee_amount',
            'net_amount',
            'available_at',
            'settlement_uuid',
            'settlement_code',
            'settlement_status',
            'settlement_scheduled_for',
            'settlement_released_at',
            'adjustment_total',
            'open_adjustment_total',
            'adjustment_types',
        ];

        fputcsv($handle, $header);

        foreach ($receivables as $receivable) {
            $adjustmentTotal = round((float) $receivable->adjustments->sum('amount'), 2);
            $openAdjustmentTotal = round((float) $receivable->adjustments
                ->whereIn('status', ['pending_recovery', 'pending_review'])
                ->sum('amount'), 2);

            fputcsv($handle, [
                $event->uuid,
                $event->name,
                $receivable->sale?->uuid,
                $receivable->sale?->codigo,
                $receivable->payment?->uuid,
                $receivable->payment?->status,
                $receivable->uuid,
                $receivable->status,
                $receivable->provider,
                $receivable->provider_charge_id,
                $receivable->provider_split_id,
                $receivable->gross_amount,
                $receivable->platform_fee_amount,
                $receivable->processor_fee_amount,
                $receivable->net_amount,
                $receivable->available_at?->toIso8601String(),
                $receivable->settlement?->uuid,
                $receivable->settlement?->code,
                $receivable->settlement?->status,
                $receivable->settlement?->scheduled_for?->toIso8601String(),
                $receivable->settlement?->released_at?->toIso8601String(),
                $adjustmentTotal,
                $openAdjustmentTotal,
                $receivable->adjustments->pluck('type')->unique()->values()->implode('|'),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return [
            'content' => $content,
            'filename' => 'bordero-evento-'.$event->uuid.'-'.now()->format('Ymd_His').'.csv',
        ];
    }

    /**
     * @return Collection<int, Receivable>
     */
    private function loadReceivables(int $tenantId, Event $event): Collection
    {
        return Receivable::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->whereNull('deleted_at')
            ->with(['sale', 'payment', 'settlement', 'adjustments'])
            ->orderBy('available_at')
            ->get();
    }

    /**
     * @param  Collection<int, Receivable>  $receivables
     * @return Collection<int, SettlementAdjustment>
     */
    private function loadAdjustments(int $tenantId, Collection $receivables): Collection
    {
        if ($receivables->isEmpty()) {
            return collect();
        }

        return SettlementAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('receivable_id', $receivables->pluck('id')->all())
            ->whereNull('deleted_at')
            ->get();
    }
}
