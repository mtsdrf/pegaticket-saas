<?php

namespace App\Services\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SettlementAdjustmentWorkflowService
{
    public function list(int $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SettlementAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['sale', 'receivable', 'settlement'])
            ->orderByDesc('id');

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

    public function summary(int $tenantId, array $filters): array
    {
        $base = SettlementAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (! empty($filters['type'])) {
            $base->where('type', $filters['type']);
        }

        if (! empty($filters['from'])) {
            $base->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $base->whereDate('created_at', '<=', $filters['to']);
        }

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as total_count, SUM(amount) as total_amount')
            ->groupBy('status')
            ->get();

        $byType = (clone $base)
            ->selectRaw('type, COUNT(*) as total_count, SUM(amount) as total_amount')
            ->groupBy('type')
            ->get();

        $openAmount = (clone $base)
            ->whereIn('status', ['pending_recovery', 'pending_review'])
            ->sum('amount');

        return [
            'by_status' => $byStatus->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->total_count,
                'amount' => round((float) $row->total_amount, 2),
            ])->values()->all(),
            'by_type' => $byType->map(fn ($row) => [
                'type' => $row->type,
                'count' => (int) $row->total_count,
                'amount' => round((float) $row->total_amount, 2),
            ])->values()->all(),
            'open_amount' => round((float) $openAmount, 2),
        ];
    }

    public function createManual(int $tenantId, array $payload): SettlementAdjustment
    {
        return DB::transaction(function () use ($tenantId, $payload) {
            $receivable = $this->findReceivable($tenantId, $payload['receivable_uuid'] ?? null);
            $settlement = $this->findSettlement(
                $tenantId,
                $payload['settlement_uuid'] ?? null,
                $receivable?->settlement?->uuid
            );

            $amount = round((float) $payload['amount'], 2);
            $delta = $payload['type'] === 'manual_credit' ? $amount : ($amount * -1);

            if ($receivable !== null) {
                $nextReceivableNet = round((float) $receivable->net_amount + $delta, 2);
                if ($nextReceivableNet < 0) {
                    abort(422, __('messages.finance.adjustment_negative_receivable'));
                }

                $receivable->net_amount = $nextReceivableNet;
                $receivable->metadata = array_merge($receivable->metadata ?? [], [
                    'last_manual_adjustment_at' => now()->toIso8601String(),
                ]);
                $receivable->save();
            }

            if ($settlement !== null) {
                $nextSettlementNet = round((float) $settlement->net_amount + $delta, 2);
                if ($nextSettlementNet < 0) {
                    abort(422, __('messages.finance.adjustment_negative_settlement'));
                }

                $settlement->net_amount = $nextSettlementNet;
                $settlement->metadata = array_merge($settlement->metadata ?? [], [
                    'last_manual_adjustment_at' => now()->toIso8601String(),
                ]);
                $settlement->save();
            }

            $adjustment = SettlementAdjustment::create([
                'tenant_id' => $tenantId,
                'settlement_id' => $settlement?->id,
                'receivable_id' => $receivable?->id,
                'sale_id' => $receivable?->sale_id,
                'type' => $payload['type'],
                'amount' => $amount,
                'reason' => $payload['reason'],
                'status' => 'applied',
                'resolution_type' => 'manual_applied',
                'resolution_notes' => $payload['resolution_notes'] ?? null,
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
                'metadata' => [
                    'origin' => 'manual_backoffice',
                    'delta' => $delta,
                ],
            ]);

            $this->writeLedger(
                tenantId: $tenantId,
                adjustment: $adjustment,
                entryType: $payload['type'],
                direction: $delta >= 0 ? 'credit' : 'debit',
                description: 'Ajuste manual operacional aplicado.',
                metadata: [
                    'reason' => $payload['reason'],
                ],
            );

            return $adjustment->fresh(['sale', 'receivable', 'settlement']);
        });
    }

    public function resolvePendingRecovery(int $tenantId, string $uuid, array $payload): SettlementAdjustment
    {
        return DB::transaction(function () use ($tenantId, $uuid, $payload) {
            $adjustment = $this->findAdjustmentForUpdate($tenantId, $uuid);

            if ($adjustment->status !== 'pending_recovery') {
                abort(422, __('messages.finance.adjustment_not_pending_recovery'));
            }

            if (! in_array($payload['resolution_type'], ['recovered_from_organizer', 'written_off_platform'], true)) {
                abort(422, __('messages.finance.adjustment_invalid_recovery_resolution'));
            }

            $adjustment->status = $payload['resolution_type'] === 'recovered_from_organizer'
                ? 'recovered'
                : 'written_off';
            $adjustment->resolution_type = $payload['resolution_type'];
            $adjustment->resolution_notes = $payload['resolution_notes'] ?? null;
            $adjustment->resolved_by = Auth::id();
            $adjustment->resolved_at = now();
            $adjustment->metadata = array_merge($adjustment->metadata ?? [], [
                'resolved_flow' => 'pending_recovery',
            ]);
            $adjustment->save();

            $this->writeLedger(
                tenantId: $tenantId,
                adjustment: $adjustment,
                entryType: $payload['resolution_type'] === 'recovered_from_organizer'
                    ? 'recovery_collected'
                    : 'platform_loss_absorbed',
                direction: $payload['resolution_type'] === 'recovered_from_organizer' ? 'credit' : 'debit',
                description: 'Ajuste pendente de recuperação resolvido.',
                metadata: [
                    'resolution_type' => $payload['resolution_type'],
                ],
            );

            return $adjustment->fresh(['sale', 'receivable', 'settlement']);
        });
    }

    public function resolvePendingReview(int $tenantId, string $uuid, array $payload): SettlementAdjustment
    {
        return DB::transaction(function () use ($tenantId, $uuid, $payload) {
            $adjustment = $this->findAdjustmentForUpdate($tenantId, $uuid);

            if ($adjustment->status !== 'pending_review') {
                abort(422, __('messages.finance.adjustment_not_pending_review'));
            }

            if (! in_array($payload['resolution_type'], ['absorb_platform_loss', 'dismissed', 'reclassify_to_recovery'], true)) {
                abort(422, __('messages.finance.adjustment_invalid_review_resolution'));
            }

            if ($payload['resolution_type'] === 'reclassify_to_recovery') {
                $adjustment->status = 'reclassified';
                $adjustment->resolution_type = $payload['resolution_type'];
                $adjustment->resolution_notes = $payload['resolution_notes'] ?? null;
                $adjustment->resolved_by = Auth::id();
                $adjustment->resolved_at = now();
                $adjustment->save();

                $recoveryAdjustment = SettlementAdjustment::create([
                    'tenant_id' => $adjustment->tenant_id,
                    'settlement_id' => $adjustment->settlement_id,
                    'receivable_id' => $adjustment->receivable_id,
                    'sale_id' => $adjustment->sale_id,
                    'sale_refund_id' => $adjustment->sale_refund_id,
                    'refund_id' => $adjustment->refund_id,
                    'type' => 'manual_recovery_after_review',
                    'amount' => $adjustment->amount,
                    'reason' => 'Exposição revisada e convertida para cobrança do organizador.',
                    'status' => 'pending_recovery',
                    'metadata' => [
                        'source_adjustment_uuid' => $adjustment->uuid,
                    ],
                ]);

                $this->writeLedger(
                    tenantId: $tenantId,
                    adjustment: $recoveryAdjustment,
                    entryType: 'review_reclassified_to_recovery',
                    direction: 'debit',
                    description: 'Ajuste pendente de revisão convertido em cobrança do organizador.',
                    metadata: [
                        'source_adjustment_uuid' => $adjustment->uuid,
                    ],
                );

                return $adjustment->fresh(['sale', 'receivable', 'settlement']);
            }

            $adjustment->status = $payload['resolution_type'] === 'dismissed' ? 'dismissed' : 'written_off';
            $adjustment->resolution_type = $payload['resolution_type'];
            $adjustment->resolution_notes = $payload['resolution_notes'] ?? null;
            $adjustment->resolved_by = Auth::id();
            $adjustment->resolved_at = now();
            $adjustment->save();

            $this->writeLedger(
                tenantId: $tenantId,
                adjustment: $adjustment,
                entryType: $payload['resolution_type'] === 'dismissed'
                    ? 'review_dismissed'
                    : 'platform_loss_absorbed',
                direction: $payload['resolution_type'] === 'dismissed' ? 'credit' : 'debit',
                description: 'Ajuste pendente de revisão resolvido.',
                metadata: [
                    'resolution_type' => $payload['resolution_type'],
                ],
            );

            return $adjustment->fresh(['sale', 'receivable', 'settlement']);
        });
    }

    private function findAdjustmentForUpdate(int $tenantId, string $uuid): SettlementAdjustment
    {
        $adjustment = SettlementAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($adjustment === null) {
            abort(404);
        }

        return $adjustment;
    }

    private function findReceivable(int $tenantId, ?string $uuid): ?Receivable
    {
        if ($uuid === null) {
            return null;
        }

        $receivable = Receivable::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($receivable === null) {
            abort(404);
        }

        return $receivable;
    }

    private function findSettlement(int $tenantId, ?string $uuid, ?string $fallbackUuid = null): ?Settlement
    {
        $searchUuid = $uuid ?? $fallbackUuid;
        if ($searchUuid === null) {
            return null;
        }

        $settlement = Settlement::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $searchUuid)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($settlement === null) {
            abort(404);
        }

        return $settlement;
    }

    private function writeLedger(
        int $tenantId,
        SettlementAdjustment $adjustment,
        string $entryType,
        string $direction,
        ?string $description = null,
        array $metadata = [],
    ): void {
        LedgerEntry::create([
            'tenant_id' => $tenantId,
            'sale_id' => $adjustment->sale_id,
            'payment_id' => $adjustment->receivable?->payment_id,
            'receivable_id' => $adjustment->receivable_id,
            'settlement_id' => $adjustment->settlement_id,
            'settlement_adjustment_id' => $adjustment->id,
            'direction' => $direction,
            'entry_type' => $entryType,
            'amount' => $adjustment->amount,
            'occurred_at' => now(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
