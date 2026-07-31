<?php

namespace Tests\Feature\Permissions;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EstadoPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado User',
            'email' => 'estado-user@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'estado-user@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];
    }

    #[Test]
    public function user_without_permission_cannot_list_estados(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/estados')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_estados(): void
    {
        $this->grantPermission('estados', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/estados')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_estado(): void
    {
        $this->grantPermission('estados', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/estados', ['name' => 'São Paulo', 'uf' => 'SP'])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_estado(): void
    {
        $this->grantPermission('estados', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/estados', ['name' => 'São Paulo', 'uf' => 'SP'])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_estado_with_duplicate_uf(): void
    {
        $this->grantPermission('estados', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/estados', ['name' => 'São Paulo', 'uf' => 'SP'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/estados', ['name' => 'Outro SP', 'uf' => 'sp'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function listing_can_be_sorted_and_filtered(): void
    {
        $this->grantPermission('estados', 'create');
        $this->grantPermission('estados', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/estados', ['name' => 'São Paulo', 'uf' => 'SP'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/estados', ['name' => 'Amazonas', 'uf' => 'AM'])
            ->assertStatus(201);

        $sorted = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/estados?sort_by=uf&sort_dir=asc')
            ->assertStatus(200);

        $sorted->assertJsonPath('data.0.uf', 'AM');
        $sorted->assertJsonPath('data.1.uf', 'SP');

        $filtered = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/estados?name=Paulo')
            ->assertStatus(200);

        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.uf', 'SP');

        $byUf = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/estados?uf=AM')
            ->assertStatus(200);

        $byUf->assertJsonCount(1, 'data');
        $byUf->assertJsonPath('data.0.name', 'Amazonas');
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        // 'name' também é unique (groups.name) — sufixo precisa entrar nos
        // dois campos, não só no slug, pra suportar >1 chamada por teste
        // (achado 2026-07-12: testes com create+read na mesma call quebravam
        // com UniqueConstraintViolationException em groups.name).
        $suffix = $functionality . '-' . $action . '-' . Str::random(6);

        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group ' . $suffix,
            'slug' => 'rbac-' . $suffix,
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
