<?php

namespace Tests\Feature\Permissions;

use App\Models\Group\Group;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Usuário base
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User',
            'email' => 'user@groups.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        // Login real
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@groups.test',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        // Grupo alvo
        $this->group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Target Group',
            'slug' => 'target-group',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_groups(): void
    {
        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/groups')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_groups(): void
    {
        $this->grantPermission('groups', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/groups')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_group(): void
    {
        $this->grantPermission('groups', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/groups', [
                'name' => 'New Group',
                'slug' => 'new-group',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_group(): void
    {
        $this->grantPermission('groups', 'create');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/groups', [
                'name' => 'New Group',
                'slug' => 'new-group',
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_without_update_permission_cannot_sync_users(): void
    {
        $this->grantPermission('groups', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson("/api/v1/groups/{$this->group->uuid}/users/sync", [
                'user_uuids' => [],
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_update_permission_can_sync_users(): void
    {
        $this->grantPermission('groups', 'update');

        // usuário válido para payload
        $targetUserId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Target',
            'email' => 'target@groups.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetUuid = DB::table('users')
            ->where('id', $targetUserId)
            ->value('uuid');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson("/api/v1/groups/{$this->group->uuid}/users/sync", [
                'user_uuids' => [$targetUuid],
            ])
            ->assertStatus(200);
    }

    /**
     * Helper padrão de RBAC (idêntico ao bloco anterior)
     */
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

        $funcId = DB::table('functionalities')->where('slug', $functionality)->value('id');

        if (!$funcId) {
            $funcId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst($functionality),
                'slug' => $functionality,
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