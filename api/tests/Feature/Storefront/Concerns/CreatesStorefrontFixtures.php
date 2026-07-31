<?php

namespace Tests\Feature\Storefront\Concerns;

use App\Models\Location\Bairro;
use App\Models\Plan\Plan;
use App\Models\Storefront\StoreBusinessHour;
use App\Models\Storefront\StoreDeliveryFee;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fixtures compartilhadas pelos testes de loja pública/checkout (Delivery
 * Fase 1) — monta um Tenant com Plan gateado pela functionality
 * 'storefront', mesmo padrão de PlanGatePermissionsTest.
 */
trait CreatesStorefrontFixtures
{
    /**
     * Guards novos do checkout (Delivery Fase 2) são fail-safe fechado por
     * padrão — testes de Fase 1 que esperam checkout bem-sucedido precisam
     * simular uma loja aberta 24h para não colidir com STORE_CLOSED.
     */
    protected function makeStoreOpenAllDay(int $tenantId): void
    {
        for ($day = 0; $day <= 6; $day++) {
            StoreBusinessHour::create([
                'tenant_id' => $tenantId,
                'day_of_week' => $day,
                'opens_at' => '00:00:00',
                'closes_at' => '23:59:59',
                'is_closed' => false,
            ]);
        }
    }

    /**
     * Bairro sem taxa cadastrada bloqueia a entrega (guard 3, Delivery
     * Fase 2) — fee=0.0 por padrão para não alterar as asserções de
     * total_amount já existentes nos testes de Fase 1.
     */
    protected function createDeliveryFeeForBairro(int $tenantId, Bairro $bairro, float $fee = 0.0): StoreDeliveryFee
    {
        return StoreDeliveryFee::create([
            'tenant_id' => $tenantId,
            'bairro_id' => $bairro->id,
            'fee' => $fee,
        ]);
    }
    protected function storefrontFunctionalityId(): int
    {
        $existing = DB::table('functionalities')->where('slug', 'storefront')->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Loja Online',
            'slug' => 'storefront',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function balcaoFunctionalityId(): int
    {
        $existing = DB::table('functionalities')->where('slug', 'balcao')->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Balcão',
            'slug' => 'balcao',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createTenantWithStorefrontPlan(bool $allowsStorefront, array $overrides = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan ' . Str::random(6),
            'slug' => 'plan-' . Str::random(8),
            'description' => 'Test plan',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        if ($allowsStorefront) {
            DB::table('plan_functionalities')->insert([
                'uuid' => (string) Str::uuid(),
                'plan_id' => $plan->id,
                'functionality_id' => $this->storefrontFunctionalityId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Tenant::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Loja ' . Str::random(6),
            'slug' => 'loja-' . Str::random(8),
            'plan_id' => $plan->id,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ], $overrides));
    }

    protected function grantBalcaoFunctionality(Tenant $tenant): void
    {
        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $tenant->plan_id,
            'functionality_id' => $this->balcaoFunctionalityId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
