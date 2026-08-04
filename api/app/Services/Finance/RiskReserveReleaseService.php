<?php

namespace App\Services\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Libera a reserva de risco retida sobre um recebivel apos o prazo
 * configurado em PlatformFinanceSettings::extra_reserve_release_offset_days.
 *
 * A liberacao credita o valor de volta ao organizador via
 * SettlementAdjustment (tipo `reserve_release`) + LedgerEntry, seguindo o
 * mesmo padrao auditavel dos ajustes manuais. Quando o settlement original
 * do recebivel ainda nao foi liberado, o credito e somado diretamente ao
 * `net_amount` desse settlement (mesmo comportamento de
 * SettlementAdjustmentWorkflowService::createManual). Quando o settlement
 * original ja foi liberado (caso mais comum, pois a reserva normalmente
 * libera depois do repasse), o ajuste fica com `settlement_id` nulo e
 * precisa ser incluido manualmente em um proximo repasse pelo backoffice —
 * a inclusao automatica em um novo settlement/PagBank split exige decisao
 * de produto adicional sobre como esse valor volta a ser transferido pelo
 * PSP e nao foi implementada nesta rodada.
 */
class RiskReserveReleaseService
{
    /**
     * @return array{reserves_seen:int,released:int,skipped:int}
     */
    public function releaseDue(?CarbonInterface $cutoffAt = null, ?int $tenantId = null, ?string $receivableUuid = null): array
    {
        $cutoffAt = ($cutoffAt ?? now())->copy();

        $query = Receivable::query()
            ->whereNull('deleted_at')
            ->where('reserve_status', 'held')
            ->where('reserve_amount', '>', 0)
            ->where('reserve_release_at', '<=', $cutoffAt)
            ->when($tenantId !== null, fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->when($receivableUuid !== null, fn ($builder) => $builder->where('uuid', $receivableUuid))
            ->orderBy('id');

        $reservesSeen = 0;
        $released = 0;
        $skipped = 0;

        foreach ($query->get() as $receivable) {
            $reservesSeen++;

            if ($this->releaseReceivableReserve((string) $receivable->uuid)) {
                $released++;

                continue;
            }

            $skipped++;
        }

        return [
            'reserves_seen' => $reservesSeen,
            'released' => $released,
            'skipped' => $skipped,
        ];
    }

    public function releaseReceivableReserve(string $receivableUuid): bool
    {
        return DB::transaction(function () use ($receivableUuid) {
            /** @var Receivable|null $receivable */
            $receivable = Receivable::query()
                ->where('uuid', $receivableUuid)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($receivable === null || $receivable->reserve_status !== 'held' || (float) $receivable->reserve_amount <= 0) {
                return false;
            }

            $reserveAmount = (float) $receivable->reserve_amount;

            $settlement = $receivable->settlement_id !== null
                ? Settlement::query()->where('id', $receivable->settlement_id)->lockForUpdate()->first()
                : null;

            $openSettlement = $settlement !== null && $settlement->status !== 'released' ? $settlement : null;

            if ($openSettlement !== null) {
                $openSettlement->net_amount = round((float) $openSettlement->net_amount + $reserveAmount, 2);
                $openSettlement->metadata = array_merge($openSettlement->metadata ?? [], [
                    'last_reserve_release_at' => now()->toIso8601String(),
                ]);
                $openSettlement->save();
            }

            $receivable->reserve_status = 'released';
            $receivable->reserve_released_at = now();
            $receivable->metadata = array_merge($receivable->metadata ?? [], [
                'reserve_released_at' => now()->toIso8601String(),
            ]);
            $receivable->save();

            $adjustment = SettlementAdjustment::create([
                'tenant_id' => $receivable->tenant_id,
                'settlement_id' => $openSettlement?->id,
                'receivable_id' => $receivable->id,
                'sale_id' => $receivable->sale_id,
                'type' => 'reserve_release',
                'amount' => $reserveAmount,
                'reason' => 'Liberacao automatica de reserva de risco apos o prazo de retencao configurado.',
                'status' => 'applied',
                'resolution_type' => 'reserve_release_auto',
                'resolved_at' => now(),
                'metadata' => [
                    'origin' => 'risk_reserve_release_service',
                    'attached_to_open_settlement' => $openSettlement !== null,
                ],
            ]);

            LedgerEntry::create([
                'tenant_id' => $receivable->tenant_id,
                'sale_id' => $receivable->sale_id,
                'payment_id' => $receivable->payment_id,
                'receivable_id' => $receivable->id,
                'settlement_id' => $openSettlement?->id,
                'settlement_adjustment_id' => $adjustment->id,
                'direction' => 'credit',
                'entry_type' => 'risk_reserve_release',
                'amount' => $reserveAmount,
                'occurred_at' => now(),
                'description' => 'Liberacao de reserva de risco retida sobre o recebivel.',
            ]);

            return true;
        });
    }
}
