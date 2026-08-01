<?php

namespace Tests\Feature\Permissions;

use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature flag por tenant individual (roadmap A5, item 19) —
 * tenant_feature_overrides tem a palavra final sobre plan_functionalities:
 * override is_enabled=true LIBERA fora do plano, is_enabled=false BLOQUEIA
 * dentro do plano. Sem override, comportamento idêntico ao gate de plano
 * já existente (ver PlanGatePermissionsTest, que continua passando sem
 * alteração).
 */
class TenantFeatureOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected int $adminUserId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('actions')->insert([
            ['key' => 'read', 'name' => 'Read', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'create', 'name' => 'Create', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'update', 'name' => 'Update', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $tenantsFunctionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresas',
            'slug' => 'tenants',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Platform Admin',
            'email' => 'admin-override@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->adminUserId = $admin->id;

        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Group Override',
            'slug' => 'admin-group-override',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['read', 'update'] as $action) {
            DB::table('group_permissions')->insert([
                'uuid' => (string) Str::uuid(),
                'group_id' => $groupId,
                'functionality_id' => $tenantsFunctionalityId,
                'action_id' => DB::table('actions')->where('key', $action)->value('id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin-override@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->adminToken = $login['access_token'];
    }

    protected function admin()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    /**
     * Dentro de um mesmo teste, o app container NÃO é reiniciado entre
     * chamadas HTTP sucessivas — o `app()->instance('tenant', ...)` que o
     * middleware `tenant` (ResolveTenant) deixa registrado numa chamada
     * tenant-scoped vaza para a chamada seguinte (mesmo sem o middleware
     * `tenant` na rota, ex.: os endpoints admin de tenants/*). Sem isso, um
     * teste que mistura chamada tenant-scoped + chamada admin no mesmo
     * método falha com PLAN_UPGRADE_REQUIRED vindo de um tenant errado
     * (leftover). Achado ao escrever este teste — não é bug do middleware
     * em produção (cada request HTTP real tem seu próprio container).
     */
    private function forgetTenantContainerBindings(): void
    {
        foreach (['tenant', 'tenant_id', 'tenant_uuid', 'tenant_user', 'tenant_role'] as $key) {
            if (app()->bound($key)) {
                app()->forgetInstance($key);
            }
        }
    }

    private function functionalityId(string $slug): int
    {
        $id = DB::table('functionalities')->where('slug', $slug)->value('id');

        if ($id) {
            return $id;
        }

        return DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($slug),
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Tenant com plano incluindo 'users' mas não 'sales' — mesmo cenário
     * base de PlanGatePermissionsTest, reaproveitado aqui para testar o
     * override por cima.
     */
    private function createTenantWithPlan(): array
    {
        $plan = Plan::create([
            'name' => 'Plan Override ' . Str::random(6),
            'slug' => 'plan-override-' . Str::random(8),
            'description' => 'Test plan',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'functionality_id' => $this->functionalityId('users'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Override Tenant',
            'slug' => 'override-tenant-' . Str::random(8),
            'plan_id' => $plan->id,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $role = TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant User Override',
            'email' => 'tenant-override-' . Str::random(6) . '@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'tenant_role_id' => $role->id,
            'is_active' => true,
        ]);

        // Permissão via tenant_role para AMBAS as functionalities usadas
        // nos testes — o gate de plano/override é testado independente do
        // RBAC de permissão em si.
        foreach (['sales', 'users'] as $slug) {
            DB::table('tenant_role_permissions')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_role_id' => $role->id,
                'functionality_id' => $this->functionalityId($slug),
                'action_id' => DB::table('actions')->where('key', 'read')->value('id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->json('data');

        $switch = $this->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/auth/switch-tenant', ['tenant_uuid' => $tenant->uuid])
            ->json('data');

        return [$tenant, $switch['access_token']];
    }

    #[Test]
    public function admin_can_sync_and_list_tenant_feature_overrides(): void
    {
        [$tenant] = $this->createTenantWithPlan();

        $sync = $this->admin()->postJson("/api/v1/tenants/{$tenant->uuid}/feature-overrides/sync", [
            'overrides' => [
                ['functionality' => 'sales', 'is_enabled' => true],
            ],
        ]);

        $sync->assertStatus(200);

        $this->assertDatabaseHas('tenant_feature_overrides', [
            'tenant_id' => $tenant->id,
        ]);

        $list = $this->admin()->getJson("/api/v1/tenants/{$tenant->uuid}/feature-overrides");

        $list->assertStatus(200)
            ->assertJsonFragment(['functionality' => 'sales', 'is_enabled' => true]);
    }

    #[Test]
    public function override_enabled_grants_access_to_a_functionality_outside_the_plan(): void
    {
        [$tenant, $token] = $this->createTenantWithPlan();

        // Sem override: plano não inclui 'sales' -> 403.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/sales')
            ->assertStatus(403)
            ->assertJson(['code' => 'PLAN_UPGRADE_REQUIRED']);

        $this->forgetTenantContainerBindings();

        $this->admin()->postJson("/api/v1/tenants/{$tenant->uuid}/feature-overrides/sync", [
            'overrides' => [
                ['functionality' => 'sales', 'is_enabled' => true],
            ],
        ])->assertStatus(200);

        // Com override habilitado: acesso liberado mesmo fora do plano.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/sales')
            ->assertStatus(200);
    }

    #[Test]
    public function override_disabled_blocks_access_to_a_functionality_inside_the_plan(): void
    {
        [$tenant, $token] = $this->createTenantWithPlan();

        // Sem override: plano inclui 'users' -> acesso liberado.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/users')
            ->assertStatus(200);

        $this->forgetTenantContainerBindings();

        $this->admin()->postJson("/api/v1/tenants/{$tenant->uuid}/feature-overrides/sync", [
            'overrides' => [
                ['functionality' => 'users', 'is_enabled' => false],
            ],
        ])->assertStatus(200);

        // Com override desabilitado: bloqueado mesmo dentro do plano.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJson(['code' => 'PLAN_UPGRADE_REQUIRED']);
    }

    #[Test]
    public function tenant_without_override_keeps_previous_plan_only_behavior(): void
    {
        [, $token] = $this->createTenantWithPlan();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/users')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/sales')
            ->assertStatus(403)
            ->assertJson(['code' => 'PLAN_UPGRADE_REQUIRED']);
    }

    #[Test]
    public function sync_and_list_require_tenants_permission(): void
    {
        [$tenant] = $this->createTenantWithPlan();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'No Perm User',
            'email' => 'no-perm-override@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'no-perm-override@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->getJson("/api/v1/tenants/{$tenant->uuid}/feature-overrides")
            ->assertStatus(403);
    }
}
