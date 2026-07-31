<?php

namespace Tests\Feature\Permissions;

use App\Models\Plan\Plan;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanPermissionsTest extends TestCase
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
            'name' => 'Plan User',
            'email' => 'plans@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'plans@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        $this->plan = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Starter plan',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_plans(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/plans')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_plans(): void
    {
        $this->grantPermission('plans', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/plans')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_update_permission_can_sync_plan_functionalities(): void
    {
        $this->grantPermission('plans', 'update');

        DB::table('functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'name' => 'Clients',
            'slug' => 'clients',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson("/api/v1/plans/{$this->plan->uuid}/functionalities/sync", [
                'functionalities' => ['clients'],
            ])
            ->assertStatus(200);
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
