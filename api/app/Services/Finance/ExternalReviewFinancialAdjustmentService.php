<?php

namespace App\Services\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
use Illuminate\Support\Facades\DB;

class ExternalReviewFinancialAdjustmentService
{
    public function handleRefundReview(Refund $refund): void
    {
        DB::transaction(function () use ($refund) {
            $refund = Refund::query()
                ->whereKey($refund->id)
                ->whereNull('deleted_at')
                ->with('payment.payable')
                ->lockForUpdate()
                ->first();

            if ($refund === null || ! $refund->payment instanceof Payment) {
                return;
            }

            $sale = $refund->payment->payable;

            if (! $sale instanceof Sale) {
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

            if (SettlementAdjustment::query()->where('refund_id', $refund->id)->whereNull('deleted_at')->exists()) {
                return;
            }

            $settlement = $receivable->settlement_id !== null
                ? Settlement::query()->whereKey($receivable->settlement_id)->lockForUpdate()->first()
                : null;

            $reviewAmount = round((float) $refund->amount, 2);
            $remainingOrganizerExposure = max(0, round((float) $receivable->net_amount, 2));
            $organizerExposureAmount = min($reviewAmount, $remainingOrganizerExposure);
            $platformExposureAmount = max(0, round($reviewAmount - $organizerExposureAmount, 2));
            $isReleased = $settlement?->status === 'released' || $receivable->status === 'released';

            if ($organizerExposureAmount > 0) {
                $adjustment = SettlementAdjustment::create([
                    'tenant_id' => $sale->tenant_id,
                    'settlement_id' => $settlement?->id,
                    'receivable_id' => $receivable->id,
                    'sale_id' => $sale->id,
                    'refund_id' => $refund->id,
                    'type' => 'chargeback_organizer_exposure',
                    'amount' => number_format($organizerExposureAmount, 2, '.', ''),
                    'reason' => $refund->reason,
                    'status' => $isReleased ? 'pending_recovery' : 'applied',
                    'metadata' => [
                        'refund_uuid' => $refund->uuid,
                        'payment_uuid' => $refund->payment->uuid,
                        'provider_refund_id' => $refund->provider_refund_id,
                    ],
                ]);

                if (! $isReleased) {
                    $this->applyOpenExposureDeduction($receivable, $settlement, $organizerExposureAmount);
                }

                $this->writeLedgerEntry(
                    receivable: $receivable,
                    settlement: $settlement,
                    adjustment: $adjustment,
                    entryType: $isReleased ? 'chargeback_recovery_pending' : 'chargeback_adjustment_applied',
                    description: $isReleased
                        ? 'Contestacao apos repasse; recuperacao pendente do organizador.'
                        : 'Contestacao abatida do repasse ainda nao liberado.',
                );
            }

            if ($platformExposureAmount > 0) {
                $adjustment = SettlementAdjustment::create([
                    'tenant_id' => $sale->tenant_id,
                    'settlement_id' => $settlement?->id,
                    'receivable_id' => $receivable->id,
                    'sale_id' => $sale->id,
                    'refund_id' => $refund->id,
                    'type' => 'chargeback_platform_exposure',
                    'amount' => number_format($platformExposureAmount, 2, '.', ''),
                    'reason' => $refund->reason,
                    'status' => 'pending_review',
                    'metadata' => [
                        'refund_uuid' => $refund->uuid,
                        'payment_uuid' => $refund->payment->uuid,
                        'provider_refund_id' => $refund->provider_refund_id,
                        'assumption' => 'platform_exposure_after_organizer_net_exhausted',
                    ],
                ]);

                $this->writeLedgerEntry(
                    receivable: $receivable,
                    settlement: $settlement,
                    adjustment: $adjustment,
                    entryType: 'chargeback_platform_exposure',
                    description: 'Contestacao excede o liquido do organizador e exige revisao financeira.',
                );
            }
        });
    }

    private function applyOpenExposureDeduction(Receivable $receivable, ?Settlement $settlement, float $amount): void
    {
        $receivable->net_amount = number_format(max(0, round((float) $receivable->net_amount - $amount, 2)), 2, '.', '');
        $receivable->metadata = array_merge($receivable->metadata ?? [], [
            'has_chargeback_adjustment' => true,
            'last_chargeback_adjustment_at' => now()->toIso8601String(),
        ]);
        $receivable->save();

        if ($settlement !== null && $settlement->status !== 'released') {
            $settlement->metadata = array_merge($settlement->metadata ?? [], [
                'has_chargeback_adjustment' => true,
                'last_chargeback_adjustment_at' => now()->toIso8601String(),
                'base_net_amount' => $settlement->metadata['base_net_amount'] ?? (float) $settlement->net_amount,
            ]);
            $settlement->net_amount = number_format(max(0, round((float) $settlement->net_amount - $amount, 2)), 2, '.', '');
            $settlement->save();
        }
    }

    private function writeLedgerEntry(
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
                'refund_id' => $adjustment->refund_id,
                'adjustment_type' => $adjustment->type,
            ],
        ]);
    }
}
