<?php

namespace Database\Seeders;

use App\Models\Plan\Plan;
use App\Models\Subscription\PlanPrice;
use Illuminate\Database\Seeder;

/**
 * Preços dos planos por período (roadmap 1B). Desconto trimestral 10% /
 * anual 20% confirmados com o usuário, congelados aqui (não hardcoded no
 * código). `amount` é o preço de tabela do período ANTES do desconto:
 *  - mensal:     preço-base
 *  - trimestral: preço-base × 3  (discount_percent = 10)
 *  - anual:      preço-base × 12 (discount_percent = 20)
 * Idempotente (updateOrCreate por plan+billing_period ativo).
 */
class PlanPricesSeeder extends Seeder
{
    public function run(): void
    {
        // Plano unico: preserva o preço-base do pacote mais completo que ja
        // vinha sendo praticado comercialmente antes da consolidacao.
        $monthlyBase = [
            'pegaticket' => 349.90,
        ];

        $periods = [
            'monthly' => ['multiplier' => 1, 'discount' => 0.0],
            'quarterly' => ['multiplier' => 3, 'discount' => 10.0],
            'yearly' => ['multiplier' => 12, 'discount' => 20.0],
        ];

        foreach ($monthlyBase as $slug => $base) {
            $plan = Plan::withTrashed()->where('slug', $slug)->first();

            if (! $plan) {
                continue;
            }

            foreach ($periods as $period => $cfg) {
                PlanPrice::withTrashed()->updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'billing_period' => $period,
                        'is_active' => true,
                    ],
                    [
                        'amount' => round($base * $cfg['multiplier'], 2),
                        'discount_percent' => $cfg['discount'],
                        'valid_from' => null,
                        'valid_to' => null,
                        'deleted_at' => null,
                    ]
                );
            }
        }

        $activePlanId = Plan::query()->where('slug', 'pegaticket')->whereNull('deleted_at')->value('id');
        if ($activePlanId !== null) {
            $activePriceIdsByPeriod = PlanPrice::query()
                ->where('plan_id', $activePlanId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->pluck('id', 'billing_period');

            foreach ($activePriceIdsByPeriod as $period => $planPriceId) {
                \DB::table('subscriptions')
                    ->whereNull('deleted_at')
                    ->where('billing_period', $period)
                    ->update([
                        'plan_id' => $activePlanId,
                        'plan_price_id' => $planPriceId,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
