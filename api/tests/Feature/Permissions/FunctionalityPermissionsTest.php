<?php

namespace Tests\Feature\Permissions;

use App\Models\User\User;
use App\Models\Functionality\Functionality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FunctionalityPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;
    protected Functionality $functionality;

    protected function setUp(): void
    {
        parent::setUp();

        // Usuário base
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User',
            'email' => 'user@func.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        // Login real
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@func.test',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        // Funcionalidade alvo
        $this->functionality = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Reports',
            'slug' => 'reports',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_functionalities(): void
    {
        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/functionalities')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_functionalities(): void
    {
        $this->grantPermission('functionalities', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/functionalities')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_functionality(): void
    {
        $this->grantPermission('functionalities', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/functionalities', [
                'name' => 'Invoices',
                'slug' => 'invoices',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_functionality(): void
    {
        $this->grantPermission('functionalities', 'create');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/functionalities', [
                'name' => 'Invoices',
                'slug' => 'invoices',
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_without_update_permission_cannot_update_functionality(): void
    {
        $this->grantPermission('functionalities', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->putJson("/api/v1/functionalities/{$this->functionality->uuid}", [
                'name' => 'Updated',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_update_permission_can_update_functionality(): void
    {
        $this->grantPermission('functionalities', 'update');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->putJson("/api/v1/functionalities/{$this->functionality->uuid}", [
                'name' => 'Updated',
            ])
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_delete_permission_can_delete_functionality(): void
    {
        $this->grantPermission('functionalities', 'delete');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->deleteJson("/api/v1/functionalities/{$this->functionality->uuid}")
            ->assertStatus(204);
    }

    /**
     * Helper RBAC padrão (idêntico aos outros blocos)
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