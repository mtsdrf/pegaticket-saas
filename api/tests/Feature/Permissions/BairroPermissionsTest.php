<?php

namespace Tests\Feature\Permissions;

use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BairroPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;
    protected Cidade $cidade;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bairro User',
            'email' => 'bairro-user@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'bairro-user@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'São Paulo',
            'uf' => 'SP',
            'is_active' => true,
        ]);

        $this->cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => 'Campinas',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_bairros(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/bairros')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_bairros(): void
    {
        $this->grantPermission('bairros', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/bairros')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_create_permission_can_create_bairro(): void
    {
        $this->grantPermission('bairros', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/bairros', [
                'name' => 'Cambuí',
                'cidade_uuid' => $this->cidade->uuid,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_bairro_with_duplicate_name(): void
    {
        $this->grantPermission('bairros', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/bairros', ['name' => 'Cambuí', 'cidade_uuid' => $this->cidade->uuid])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/bairros', ['name' => 'Cambuí', 'cidade_uuid' => $this->cidade->uuid])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function listing_can_be_sorted_and_filtered_by_cidade_name_and_cidade_uuid_cascade(): void
    {
        $this->grantPermission('bairros', 'create');
        $this->grantPermission('bairros', 'read');

        $outraCidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $this->cidade->estado_id,
            'name' => 'Amparo',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/bairros', ['name' => 'Cambuí', 'cidade_uuid' => $this->cidade->uuid])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->postJson('/api/v1/bairros', ['name' => 'Centro', 'cidade_uuid' => $outraCidade->uuid])
            ->assertStatus(201);

        // sort_by=cidade_name (leftJoin) — Amparo < Campinas
        $sorted = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/bairros?sort_by=cidade_name&sort_dir=asc')
            ->assertStatus(200);

        $sorted->assertJsonPath('data.0.name', 'Centro');
        $sorted->assertJsonPath('data.1.name', 'Cambuí');

        // filtro contains por cidade_name
        $filtered = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/bairros?cidade_name=Amparo')
            ->assertStatus(200);

        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.name', 'Centro');

        // filtro exato pré-existente (cascade do dropdown) continua funcionando
        $byCidadeUuid = $this->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/bairros?cidade_uuid=' . $this->cidade->uuid)
            ->assertStatus(200);

        $byCidadeUuid->assertJsonCount(1, 'data');
        $byCidadeUuid->assertJsonPath('data.0.name', 'Cambuí');
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
