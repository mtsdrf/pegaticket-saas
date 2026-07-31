<?php

namespace Tests\Feature\Permissions;

use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use App\Services\Tenant\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * `dashboard:read` (Visão Geral) foi separada de `reports:read` em
 * 2026-07-13 — antes, /reports/indicators e /reports/charts eram
 * protegidas pela mesma permissão usada por Análises e pelos relatórios
 * de pedidos/clientes/recebíveis. Estes testes provam que a nova
 * permissão é realmente independente (não só cosmética) e que o backfill
 * do role `owner` de tenants já existentes funciona.
 */
class DashboardPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('dashboard-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function user_with_reports_read_but_without_dashboard_read_cannot_view_indicators_or_charts(): void
    {
        $this->grantPermission('reports', 'read');

        $this->auth()->getJson('/api/v1/reports/indicators')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);

        $this->auth()->getJson('/api/v1/reports/charts')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    #[Test]
    public function user_with_dashboard_read_but_without_reports_read_can_view_indicators_and_charts(): void
    {
        $this->grantPermission('dashboard', 'read');

        $this->auth()->getJson('/api/v1/reports/indicators')->assertStatus(200);
        $this->auth()->getJson('/api/v1/reports/charts')->assertStatus(200);

        // Continua sem acesso aos demais relatórios, que exigem `reports:read`.
        $this->auth()->getJson('/api/v1/reports/orders')->assertStatus(403);
    }

    #[Test]
    public function user_with_dashboard_read_but_without_reports_read_still_blocked_from_other_report_endpoints(): void
    {
        $this->grantPermission('dashboard', 'read');

        $this->auth()->getJson('/api/v1/reports/clients')->assertStatus(403);
        $this->auth()->getJson('/api/v1/reports/receivables')->assertStatus(403);
        $this->auth()->getJson('/api/v1/reports/receivables/summary')->assertStatus(403);
    }

    #[Test]
    public function backfill_grants_dashboard_read_to_owner_role_of_pre_existing_tenant(): void
    {
        // Simula uma tenant provisionada ANTES da functionality `dashboard`
        // existir: plano já tinha `reports`, mas não `dashboard`.
        $plan = Plan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Plano Existente',
            'slug' => 'plano-existente-' . Str::random(6),
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $reportsFunctionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Relatórios',
            'slug' => 'reports',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'functionality_id' => $reportsFunctionalityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $readActionId = DB::table('actions')->insertGetId([
            'key' => 'read',
            'name' => 'Visualizar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Pré-Existente',
            'slug' => 'tenant-pre-existente-' . Str::random(6),
            'plan_id' => $plan->id,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $provisioning = app(TenantProvisioningService::class);
        $ownerRole = $provisioning->createOwnerRole($tenant);
        $provisioning->attachOwnerUser($tenant, $this->userId, $ownerRole);
        $provisioning->syncOwnerRolePermissions($tenant, $ownerRole);

        $this->assertFalse(
            DB::table('tenant_role_permissions')
                ->join('functionalities', 'functionalities.id', '=', 'tenant_role_permissions.functionality_id')
                ->where('tenant_role_permissions.tenant_role_id', $ownerRole->id)
                ->where('functionalities.slug', 'dashboard')
                ->exists(),
            'Owner não deveria ter dashboard:read antes da functionality existir no plano.'
        );

        // Deploy: nova functionality `dashboard` passa a existir e é
        // adicionada ao plano já em uso pela tenant (equivalente a rodar
        // FunctionalitiesSeeder + InitialPlansSeeder em produção).
        $dashboardFunctionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Visão Geral',
            'slug' => 'dashboard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'functionality_id' => $dashboardFunctionalityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sem o backfill, o owner de uma tenant já existente não ganharia
        // a permissão nova automaticamente — é isso que o comando de
        // backfill de uso único resolve, chamando syncOwnerRolePermissions()
        // de novo para cada tenant ativa.
        $provisioning->syncOwnerRolePermissions($tenant, $ownerRole);

        $this->assertTrue(
            DB::table('tenant_role_permissions')
                ->join('functionalities', 'functionalities.id', '=', 'tenant_role_permissions.functionality_id')
                ->where('tenant_role_permissions.tenant_role_id', $ownerRole->id)
                ->where('functionalities.slug', 'dashboard')
                ->where('tenant_role_permissions.action_id', $readActionId)
                ->exists()
        );

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'dashboard-user@test.com',
            'password' => 'password123',
        ])->json('data');

        $switch = $this->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/auth/switch-tenant', [
                'tenant_uuid' => $tenant->uuid,
            ]);
        $switch->assertStatus(200);
        $ownerToken = $switch->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer ' . $ownerToken)
            ->getJson('/api/v1/reports/indicators')
            ->assertStatus(200);
    }
}
