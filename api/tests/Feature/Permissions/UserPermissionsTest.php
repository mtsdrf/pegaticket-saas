<?php

namespace Tests\Feature\Permissions;

use App\Models\User\User;
use App\Models\Group\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Usuário base
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        // Login real (JWT)
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];
    }

    #[Test]
    public function user_without_permission_cannot_list_users(): void
    {
        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_users(): void
    {
        $this->grantPermission('users', 'read');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_user(): void
    {
        $this->grantPermission('users', 'read');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/users', [
                'name' => 'X',
                'email' => 'x@test.com',
                'password' => 'Password123!',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_user(): void
    {
        $this->grantPermission('users', 'create');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/users', [
                'name' => 'New',
                'email' => 'new@test.com',
                'password' => 'Password123!',
            ]);

        $response->assertStatus(201);
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        // Grupo
        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group-' . $action,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Vincular usuário ao grupo
        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Funcionalidade
        $funcId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($functionality),
            'slug' => $functionality,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Action (cria se não existir)
        $actionId = DB::table('actions')
            ->where('key', $action)
            ->value('id');

        if (!$actionId) {
            $actionId = DB::table('actions')->insertGetId([
                'key' => $action,
                'name' => ucfirst($action),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Permissão
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