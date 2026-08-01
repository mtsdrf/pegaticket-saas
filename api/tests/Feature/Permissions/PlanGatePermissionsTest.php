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

class PlanGatePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected string $accessToken;
    protected Tenant $tenant;
    protected TenantRole $role;
    protected int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('actions')->insert([
            ['key' => 'read', 'name' => 'Read', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'create', 'name' => 'Create', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $ordersFunctionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Orders',
            'slug' => 'sales',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $usersFunctionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Users',
            'slug' => 'users',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plan = Plan::firstOrCreate(
            ['slug' => 'basic-gate'],
            [
                'name' => 'Basic',
                'description' => 'Basic',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'functionality_id' => $usersFunctionalityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'plan_id' => $plan->id,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->role = TenantRole::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant User',
            'email' => 'tenant-gate@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        TenantUser::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->userId,
            'tenant_role_id' => $this->role->id,
            'is_active' => true,
        ]);

        DB::table('tenant_role_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_role_id' => $this->role->id,
            'functionality_id' => $ordersFunctionalityId,
            'action_id' => DB::table('actions')->where('key', 'read')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'tenant-gate@test.com',
            'password' => 'password123',
        ])->json('data');

        $switch = $this->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/auth/switch-tenant', [
                'tenant_uuid' => $this->tenant->uuid,
            ])->json('data');

        $this->accessToken = $switch['access_token'];
    }

    #[Test]
    public function tenant_user_receives_upgrade_required_when_permission_exists_but_plan_does_not_allow_feature(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/sales')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'PLAN_UPGRADE_REQUIRED',
            ]);
    }
}
