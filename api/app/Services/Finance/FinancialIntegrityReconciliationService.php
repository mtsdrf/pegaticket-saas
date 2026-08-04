<?php

namespace App\Services\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Tenant\Tenant;

class FinancialIntegrityReconciliationService
{
    public function buildReport(?int $tenantId = null): array
    {
        $tenantIds = $tenantId !== null
            ? [$tenantId]
            : Tenant::query()->whereNull('deleted_at')->pluck('id')->all();

        $report = [
            'summary' => [
                'receivables_without_settlement' => 0,
                'settlement_net_mismatches' => 0,
                'released_settlements_missing_ledger' => 0,
                'open_adjustments' => 0,
            ],
            'issues' => [
                'receivables_without_settlement' => [],
                'settlement_net_mismatches' => [],
                'released_settlements_missing_ledger' => [],
                'open_adjustments' => [],
            ],
        ];

        foreach ($tenantIds as $currentTenantId) {
            $report = $this->appendReceivableIssues($report, $currentTenantId);
            $report = $this->appendSettlementIssues($report, $currentTenantId);
            $report = $this->appendAdjustmentIssues($report, $currentTenantId);
        }

        return $report;
    }

    private function appendReceivableIssues(array $report, int $tenantId): array
    {
        $items = Receivable::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('settlement_id')
            ->where('available_at', '<=', now())
            ->get(['uuid', 'status', 'sale_id', 'available_at']);

        foreach ($items as $item) {
            $report['summary']['receivables_without_settlement']++;
            $report['issues']['receivables_without_settlement'][] = [
                'tenant_id' => $tenantId,
                'receivable_uuid' => $item->uuid,
                'status' => $item->status,
                'available_at' => $item->available_at,
            ];
        }

        return $report;
    }

    private function appendSettlementIssues(array $report, int $tenantId): array
    {
        $settlements = Settlement::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['receivables', 'adjustments'])
            ->get();

        foreach ($settlements as $settlement) {
            $receivableNet = round((float) $settlement->receivables->sum(fn ($receivable) => (float) $receivable->net_amount), 2);
            $appliedAdjustments = round((float) $settlement->adjustments
                ->filter(fn ($adjustment) => $adjustment->status === 'applied' && in_array($adjustment->type, ['manual_credit', 'manual_debit'], true))
                ->sum(fn ($adjustment) => $adjustment->type === 'manual_credit'
                    ? (float) $adjustment->amount
                    : ((float) $adjustment->amount * -1)), 2);
            $expectedNet = round($receivableNet, 2);

            if (abs($expectedNet - (float) $settlement->net_amount) > 0.009) {
                $report['summary']['settlement_net_mismatches']++;
                $report['issues']['settlement_net_mismatches'][] = [
                    'tenant_id' => $tenantId,
                    'settlement_uuid' => $settlement->uuid,
                    'settlement_code' => $settlement->code,
                    'recorded_net_amount' => round((float) $settlement->net_amount, 2),
                    'expected_net_amount' => $expectedNet,
                    'manual_adjustments_net_effect' => $appliedAdjustments,
                ];
            }

            if ($settlement->status === 'released') {
                $hasLedger = LedgerEntry::query()
                    ->where('settlement_id', $settlement->id)
                    ->where('entry_type', 'settlement_release')
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $hasLedger) {
                    $report['summary']['released_settlements_missing_ledger']++;
                    $report['issues']['released_settlements_missing_ledger'][] = [
                        'tenant_id' => $tenantId,
                        'settlement_uuid' => $settlement->uuid,
                        'settlement_code' => $settlement->code,
                    ];
                }
            }
        }

        return $report;
    }

    private function appendAdjustmentIssues(array $report, int $tenantId): array
    {
        $items = SettlementAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending_recovery', 'pending_review'])
            ->get(['uuid', 'type', 'amount', 'status', 'created_at']);

        foreach ($items as $item) {
            $report['summary']['open_adjustments']++;
            $report['issues']['open_adjustments'][] = [
                'tenant_id' => $tenantId,
                'adjustment_uuid' => $item->uuid,
                'type' => $item->type,
                'amount' => round((float) $item->amount, 2),
                'status' => $item->status,
                'created_at' => $item->created_at,
            ];
        }

        return $report;
    }
}
