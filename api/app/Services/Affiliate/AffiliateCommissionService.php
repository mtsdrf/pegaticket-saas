<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate\AffiliateCommission;
use App\Models\Sale\Sale;
use App\Models\Tenant\TenantSettings;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo/registro de comissão de afiliado, disparado por SalePaid (mesmo
 * gatilho de CreateReceivableOnSalePaid). Percentual resolvido em cascata:
 * Affiliate.commission_percentage (override por afiliado) ->
 * TenantSettings.affiliate_default_commission_percentage (default do
 * tenant) -> nenhum dos dois configurado = nenhuma comissão gerada
 * (estado neutro, mesmo espírito opt-in de
 * PlatformFinanceSettings.extra_reserve_enabled). Não há "default técnico"
 * de percentual global aqui de propósito — inventar um número (ex: 5%)
 * sem validação do usuário seria pior que não gerar comissão nenhuma.
 * Pagamento real da comissão ao afiliado é extensão futura (Finance).
 */
class AffiliateCommissionService
{
    public function generateForSaleUuid(string $saleUuid): ?AffiliateCommission
    {
        return DB::transaction(function () use ($saleUuid) {
            $sale = Sale::query()
                ->where('uuid', $saleUuid)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($sale === null || ! $sale->is_paid || $sale->affiliate_id === null) {
                return null;
            }

            $existing = AffiliateCommission::query()
                ->where('sale_id', $sale->id)
                ->whereNull('deleted_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $affiliate = $sale->affiliate()->first();

            if ($affiliate === null) {
                return null;
            }

            $percentage = $this->resolvePercentage($sale->tenant_id, $affiliate->commission_percentage);

            if ($percentage === null || $percentage <= 0) {
                return null;
            }

            $saleAmount = round((float) $sale->total_amount, 2);
            $amount = round($saleAmount * ($percentage / 100), 2);

            return AffiliateCommission::create([
                'tenant_id' => $sale->tenant_id,
                'affiliate_id' => $affiliate->id,
                'sale_id' => $sale->id,
                'sale_amount' => number_format($saleAmount, 2, '.', ''),
                'percentage_applied' => number_format($percentage, 2, '.', ''),
                'amount' => number_format($amount, 2, '.', ''),
                'status' => AffiliateCommission::STATUS_PENDING,
            ]);
        });
    }

    private function resolvePercentage(int $tenantId, ?float $affiliatePercentage): ?float
    {
        if ($affiliatePercentage !== null) {
            return (float) $affiliatePercentage;
        }

        $tenantDefault = TenantSettings::where('tenant_id', $tenantId)->value('affiliate_default_commission_percentage');

        return $tenantDefault !== null ? (float) $tenantDefault : null;
    }
}
