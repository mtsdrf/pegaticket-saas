<?php

namespace App\Services\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleRefund;
use Illuminate\Support\Facades\DB;

class SaleRefundFinancialAdjustmentService
{
    public function handleRefund(string $saleUuid, string $saleRefundUuid): void
    {
        DB::transaction(function () use ($saleUuid, $saleRefundUuid) {
            $sale = Sale::query()
                ->where('uuid', $saleUuid)
                ->whereNull('deleted_at')
                ->first();

            $refund = SaleRefund::query()
                ->where('uuid', $saleRefundUuid)
                ->whereNull('deleted_at')
                ->first();

            if ($sale === null || $refund === null) {
                return;
            }

            $receivable = Receivable::query()
                ->where('sale_id', $sale->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($receivable === null) {
                return;
            }

            $existing = SettlementAdjustment::query()
                ->where('sale_refund_id', $refund->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($existing) {
                return;
            }

            $settlement = $receivable->settlement_id !== null
                ? Settlement::query()->whereKey($receivable->settlement_id)->lockForUpdate()->first()
                : null;

            $refundAmount = round((float) $refund->amount, 2);
            $remainingOrganizerExposure = $this->resolveRemainingOrganizerExposure($receivable);
            $organizerDeductionAmount = min($refundAmount, $remainingOrganizerExposure);
            $platformExposureAmount = max(0, round($refundAmount - $organizerDeductionAmount, 2));

            if ($organizerDeductionAmount > 0) {
                $isReleased = $settlement?->status === 'released' || $receivable->status === 'released';
                $status = $isReleased ? 'pending_recovery' : 'applied';

                $adjustment = SettlementAdjustment::create([
                    'tenant_id' => $sale->tenant_id,
                    'settlement_id' => $settlement?->id,
                    'receivable_id' => $receivable->id,
                    'sale_id' => $sale->id,
                    'sale_refund_id' => $refund->id,
                    'type' => 'refund_organizer_deduction',
                    'amount' => number_format($organizerDeductionAmount, 2, '.', ''),
                    'reason' => $refund->reason,
                    'status' => $status,
                    'metadata' => [
                        'sale_refund_uuid' => $refund->uuid,
                        'sale_uuid' => $sale->uuid,
                    ],
                ]);

                if (! $isReleased) {
                    $this->applyOpenReceivableDeduction($receivable, $settlement, $organizerDeductionAmount);
                }

                $this->writeAdjustmentLedgerEntry(
                    receivable: $receivable,
                    settlement: $settlement,
                    adjustment: $adjustment,
                    entryType: $isReleased ? 'refund_recovery_pending' : 'refund_adjustment_applied',
                    description: $isReleased
                        ? 'Estorno registrado apos repasse; recuperacao pendente do organizador.'
                        : 'Estorno abatido do repasse ainda nao liberado.',
                );
            }

            if ($platformExposureAmount > 0) {
                $adjustment = SettlementAdjustment::create([
                    'tenant_id' => $sale->tenant_id,
                    'settlement_id' => $settlement?->id,
                    'receivable_id' => $receivable->id,
                    'sale_id' => $sale->id,
                    'sale_refund_id' => $refund->id,
                    'type' => 'refund_platform_exposure',
                    'amount' => number_format($platformExposureAmount, 2, '.', ''),
                    'reason' => $refund->reason,
                    'status' => 'pending_review',
                    'metadata' => [
                        'sale_refund_uuid' => $refund->uuid,
                        'sale_uuid' => $sale->uuid,
                        'assumption' => 'platform_exposure_after_organizer_net_exhausted',
                    ],
                ]);

                $this->writeAdjustmentLedgerEntry(
                    receivable: $receivable,
                    settlement: $settlement,
                    adjustment: $adjustment,
                    entryType: 'refund_platform_exposure',
                    description: 'Estorno excede o liquido do organizador e exige revisao financeira.',
                );
            }
        });
    }

    private function resolveRemainingOrganizerExposure(Receivable $receivable): float
    {
        $netAmount = round((float) $receivable->net_amount, 2);

        if ($netAmount <= 0) {
            return 0.0;
        }

        return $netAmount;
    }

    private function applyOpenReceivableDeduction(Receivable $receivable, ?Settlement $settlement, float $amount): void
    {
        $receivable->net_amount = number_format(max(0, round((float) $receivable->net_amount - $amount, 2)), 2, '.', '');
        $receivable->metadata = array_merge($receivable->metadata ?? [], [
            'has_refund_adjustment' => true,
            'last_refund_adjustment_at' => now()->toIso8601String(),
        ]);
        $receivable->save();

        if ($settlement !== null && $settlement->status !== 'released') {
            $settlement->metadata = array_merge($settlement->metadata ?? [], [
                'has_refund_adjustment' => true,
                'last_refund_adjustment_at' => now()->toIso8601String(),
                'base_net_amount' => $settlement->metadata['base_net_amount'] ?? (float) $settlement->net_amount,
            ]);
            $settlement->net_amount = number_format(max(0, round((float) $settlement->net_amount - $amount, 2)), 2, '.', '');
            $settlement->save();
        }
    }

    private function writeAdjustmentLedgerEntry(
        Receivable $receivable,
        ?Settlement $settlement,
        SettlementAdjustment $adjustment,
        string $entryType,
        string $description,
    ): void {
        LedgerEntry::create([
            'tenant_id' => $receivable->tenant_id,
            'sale_id' => $receivable->sale_id,
            'receivable_id' => $receivable->id,
            'settlement_id' => $settlement?->id,
            'settlement_adjustment_id' => $adjustment->id,
            'direction' => 'debit',
            'entry_type' => $entryType,
            'amount' => $adjustment->amount,
            'occurred_at' => now(),
            'description' => $description,
            'metadata' => [
                'sale_refund_id' => $adjustment->sale_refund_id,
                'adjustment_type' => $adjustment->type,
            ],
        ]);
    }
}
