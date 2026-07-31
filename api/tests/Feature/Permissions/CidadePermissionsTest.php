<?php

namespace Tests\Feature\Permissions;

use App\Models\Location\Estado;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CidadePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;
    protected Estado $estado;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cidade User',
            'email' => 'cidade-user@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'cidade-user@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        $this->estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'São Paulo',
            'uf' => 'SP',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_cidades(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/cidades')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_cidades(): void
    {
        $this->grantPermission('cidades', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/cidades')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_create_permission_can_create_cidade(): void
    {
        $this->grantPermission('cidades', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/cidades', [
                'name' => 'Campinas',
                'estado_uuid' => $this->estado->uuid,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_cidade_with_duplicate_name(): void
    {
        $this->grantPermission('cidades', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/cidades', ['name' => 'Campinas', 'estado_uuid' => $this->estado->uuid])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/cidades', ['name' => 'Campinas', 'estado_uuid' => $this->estado->uuid])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function user_cannot_create_cidade_with_invalid_estado(): void
    {
        $this->grantPermission('cidades', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/cidades', [
                'name' => 'Campinas',
                'estado_uuid' => (string) Str::uuid(),
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function listing_can_be_sorted_and_filtered_by_estado_name_and_estado_uuid_cascade(): void
    {
        $this->grantPermission('cidades', 'create');
        $this->grantPermission('cidades', 'read');

        $outroEstado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Amazonas',
            'uf' => 'AM',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/cidades', ['name' => 'Campinas', 'estado_uuid' => $this->estado->uuid])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/cidades', ['name' => 'Manaus', 'estado_uuid' => $outroEstado->uuid])
            ->assertStatus(201);

        // sort_by=estado_name (leftJoin) — Amazonas < São Paulo
        $sorted = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/cidades?sort_by=estado_name&sort_dir=asc')
            ->assertStatus(200);

        $sorted->assertJsonPath('data.0.name', 'Manaus');
        $sorted->assertJsonPath('data.1.name', 'Campinas');

        // filtro contains por estado_name
        $filtered = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/cidades?estado_name=Amazo')
            ->assertStatus(200);

        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.name', 'Manaus');

        // filtro exato pré-existente (cascade do dropdown) continua funcionando
        $byEstadoUuid = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/cidades?estado_uuid=' . $this->estado->uuid)
            ->assertStatus(200);

        $byEstadoUuid->assertJsonCount(1, 'data');
        $byEstadoUuid->assertJsonPath('data.0.name', 'Campinas');
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        // 'name' também é unique (groups.name) — sufixo precisa entrar nos
        // dois campos, não só no slug, pra suportar >1 chamada por teste.
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
