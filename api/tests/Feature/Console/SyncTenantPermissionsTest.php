<?php

namespace Tests\Feature\Console;

use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncTenantPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sync Test User',
            'email' => 'sync-test@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'sync-test@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];
        $this->plan = Plan::firstOrCreate(
            ['slug' => 'premium'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Premium',
                'description' => 'Premium',
                'sort_order' => 30,
                'is_active' => true,
            ]
        );

        $this->grantPermission('tenants', 'create');
        $this->insertFunctionality('clients');
    }

    protected function createTenantViaApi(string $slug): Tenant
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/tenants', [
                'name' => 'Tenant ' . $slug,
                'slug' => $slug,
                'plan_uuid' => $this->plan->uuid,
            ])
            ->assertStatus(201);

        return Tenant::where('slug', $slug)->firstOrFail();
    }

    protected function ownerRole(Tenant $tenant): TenantRole
    {
        return TenantRole::where('tenant_id', $tenant->id)->where('slug', 'owner')->firstOrFail();
    }

    #[Test]
    public function command_backfills_permissions_for_functionality_added_after_tenant_creation(): void
    {
        $tenant = $this->createTenantViaApi('acme');
        $ownerRole = $this->ownerRole($tenant);

        // 'clients' já existia na criação do tenant — Owner já tem acesso.
        $this->assertDatabaseHas('tenant_role_permissions', [
            'tenant_role_id' => $ownerRole->id,
        ]);

        // Módulo novo, "lançado" depois que o tenant já existia.
        $this->insertFunctionality('stock');

        $stockFuncId = DB::table('functionalities')->where('slug', 'stock')->value('id');

        $this->assertDatabaseMissing('tenant_role_permissions', [
            'tenant_role_id' => $ownerRole->id,
            'functionality_id' => $stockFuncId,
        ]);

        $this->artisan('tenants:sync-permissions')->assertExitCode(0);

        $actionCount = DB::table('actions')->count();

        $this->assertEquals(
            $actionCount,
            DB::table('tenant_role_permissions')
                ->where('tenant_role_id', $ownerRole->id)
                ->where('functionality_id', $stockFuncId)
                ->count()
        );
    }

    #[Test]
    public function running_the_command_twice_does_not_duplicate_permissions(): void
    {
        $tenant = $this->createTenantViaApi('acme');
        $ownerRole = $this->ownerRole($tenant);

        $this->insertFunctionality('stock');

        $this->artisan('tenants:sync-permissions')->assertExitCode(0);
        $countAfterFirstRun = DB::table('tenant_role_permissions')
            ->where('tenant_role_id', $ownerRole->id)
            ->count();

        $this->artisan('tenants:sync-permissions')->assertExitCode(0);
        $countAfterSecondRun = DB::table('tenant_role_permissions')
            ->where('tenant_role_id', $ownerRole->id)
            ->count();

        $this->assertEquals($countAfterFirstRun, $countAfterSecondRun);
    }

    #[Test]
    public function tenant_option_restricts_sync_to_a_single_tenant(): void
    {
        $tenantA = $this->createTenantViaApi('acme');
        $tenantB = $this->createTenantViaApi('globex');

        $this->insertFunctionality('stock');
        $stockFuncId = DB::table('functionalities')->where('slug', 'stock')->value('id');

        $this->artisan('tenants:sync-permissions', ['--tenant' => $tenantA->uuid])->assertExitCode(0);

        $this->assertDatabaseHas('tenant_role_permissions', [
            'tenant_role_id' => $this->ownerRole($tenantA)->id,
            'functionality_id' => $stockFuncId,
        ]);

        $this->assertDatabaseMissing('tenant_role_permissions', [
            'tenant_role_id' => $this->ownerRole($tenantB)->id,
            'functionality_id' => $stockFuncId,
        ]);
    }

    /**
     * Cria a functionality e a vincula ao plano do cenário (premium):
     * o sync do Owner filtra por plan_functionalities, então uma
     * functionality fora do plano nunca chega ao role Owner.
     */
    protected function insertFunctionality(string $slug): void
    {
        $funcId = DB::table('functionalities')->where('slug', $slug)->value('id');

        if (!$funcId) {
            $funcId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst($slug),
                'slug' => $slug,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $pivotExists = DB::table('plan_functionalities')
            ->where('plan_id', $this->plan->id)
            ->where('functionality_id', $funcId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$pivotExists) {
            DB::table('plan_functionalities')->insert([
                'uuid' => (string) Str::uuid(),
                'plan_id' => $this->plan->id,
                'functionality_id' => $funcId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('actions')->count() === 0) {
            foreach (['read', 'create', 'update', 'delete'] as $key) {
                DB::table('actions')->insert([
                    'key' => $key,
                    'name' => ucfirst($key),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group',
            'slug' => 'rbac-' . $functionality . '-' . $action . '-' . Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $funcId = DB::table('functionalities')->where('slug', $functionality)->value('id');

        if (!$funcId) {
            $funcId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst($functionality),
                'slug' => $functionality,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $actionId = DB::table('actions')->where('key', $action)->value('id');

        if (!$actionId) {
            $actionId = DB::table('actions')->insertGetId([
                'key' => $action,
                'name' => ucfirst($action),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('group_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'functionality_id' => $funcId,
            'action_id' => $actionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
