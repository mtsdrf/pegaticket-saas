<?php

namespace Tests\Feature\Storefront\Concerns;

use App\Models\Plan\Plan;
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
    protected function storefrontFunctionalityId(): int
    {
        $existing = DB::table('functionalities')->where('slug', 'storefront')->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bilheteria Online',
            'slug' => 'storefront',
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
            'name' => 'Bilheteria ' . Str::random(6),
            'slug' => 'loja-' . Str::random(8),
            'plan_id' => $plan->id,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ], $overrides));
    }
}
