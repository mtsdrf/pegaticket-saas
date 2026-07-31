<?php

namespace Tests\Feature\Permissions;

use App\Models\Plan\Plan;
use App\Models\User\User;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;
    protected Tenant $tenant;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant User',
            'email' => 'tenant@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'tenant@test.com',
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

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Main Tenant',
            'slug' => 'main-tenant',
            'plan_id' => $this->plan->id,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_tenants(): void
    {
        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/tenants')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_tenants(): void
    {
        $this->grantPermission('tenants', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/tenants')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_create_permission_can_create_tenant(): void
    {
        $this->grantPermission('tenants', 'create');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/tenants', [
                'name' => 'New Tenant',
                'slug' => 'new-tenant',
                'plan_uuid' => $this->plan->uuid,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_with_update_permission_can_update_tenant(): void
    {
        $this->grantPermission('tenants', 'update');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->putJson("/api/v1/tenants/{$this->tenant->uuid}", [
                'name' => 'Updated Name',
                'plan_uuid' => $this->plan->uuid,
                'is_active' => true,
            ])
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_delete_permission_can_delete_tenant(): void
    {
        $this->grantPermission('tenants', 'delete');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->deleteJson("/api/v1/tenants/{$this->tenant->uuid}")
            ->assertStatus(204);
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group',
            'slug' => 'rbac-' . $functionality . '-' . $action,
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

        $funcId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($functionality),
            'slug' => $functionality,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
