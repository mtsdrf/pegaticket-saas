<?php

namespace Tests\Feature\Subscription\Concerns;

use App\Models\Plan\Plan;
use App\Models\Subscription\PlanPrice;
use App\Models\Subscription\Subscription;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesSubscriptionFixtures
{
    protected function createPlan(string $slug = 'pegaticket'): Plan
    {
        return Plan::create([
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($slug),
            'slug' => $slug . '-' . Str::random(5),
            'is_active' => true,
        ]);
    }

    protected function createPlanPrice(Plan $plan, string $period = 'monthly', float $amount = 99.90, float $discount = 0): PlanPrice
    {
        return PlanPrice::create([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'billing_period' => $period,
            'amount' => $amount,
            'discount_percent' => $discount,
            'is_active' => true,
        ]);
    }

    protected function createTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Fixture Tenant',
            'slug' => 'fixture-' . Str::random(8),
            'is_active' => true,
        ]);
    }

    /**
     * Vincula um usuário PROPRIETÁRIO (tenant_roles.slug=owner) ao tenant —
     * resolveOwnerEmail() no MercadoPagoPaymentProvider depende disso para
     * o payer_email da cobrança de assinatura.
     */
    protected function createTenantOwner(Tenant $tenant, string $email): User
    {
        $owner = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Owner',
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $role = TenantRole::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'owner'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Proprietário', 'is_active' => true]
        );

        TenantUser::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'tenant_role_id' => $role->id,
            'is_active' => true,
        ]);

        return $owner;
    }

    protected function createSubscription(array $attributes = []): Subscription
    {
        $plan = $attributes['plan'] ?? $this->createPlan();
        $period = $attributes['billing_period'] ?? 'monthly';
        $planPrice = $attributes['plan_price'] ?? $this->createPlanPrice($plan, $period);
        $tenantId = $attributes['tenant_id'] ?? $this->createTenant()->id;

        return Subscription::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'plan_id' => $plan->id,
            'plan_price_id' => $planPrice->id,
            'billing_period' => $period,
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
            'next_charge_at' => now()->addDays(14),
            'auto_renew' => true,
        ], array_diff_key($attributes, array_flip(['plan', 'plan_price', 'billing_period']))));
    }
}
