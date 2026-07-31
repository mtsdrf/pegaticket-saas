<?php

namespace Tests\Feature\Permissions;

use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class EnderecoPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected Estado $estado;
    protected Cidade $cidade;
    protected Bairro $bairro;

    protected function setUp(): void
    {
        parent::setUp();

        // EnderecoService::create() dispara GeocodeEnderecoJob síncrono em
        // testing — nenhum teste aqui verifica lat/lng, só precisa não bater
        // na API real (ver Http::preventStrayRequests() em tests/TestCase.php).
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $this->setUpTenantScopedUser('endereco-user@test.com');

        $this->estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'São Paulo',
            'uf' => 'SP',
            'is_active' => true,
        ]);

        $this->cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $this->estado->id,
            'name' => 'Campinas',
            'is_active' => true,
        ]);

        $this->bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $this->cidade->id,
            'name' => 'Cambuí',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_enderecos(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/enderecos')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_enderecos(): void
    {
        $this->grantPermission('enderecos', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/enderecos')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_create_permission_can_create_endereco(): void
    {
        $this->grantPermission('enderecos', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua das Flores, 100',
                'cep' => '13010-000',
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $this->cidade->uuid,
                'bairro_uuid' => $this->bairro->uuid,
            ])
            ->assertStatus(201);
    }

    /**
     * Fase 8 (migração de dados reais): legado guardava numero/complemento
     * no cliente, não no endereço. No sistema novo moram no Endereco.
     */
    #[Test]
    public function numero_and_complemento_are_persisted_and_returned(): void
    {
        $this->grantPermission('enderecos', 'create');
        $this->grantPermission('enderecos', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua das Flores, 100',
                'numero' => '123A',
                'complemento' => 'Fundos',
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $this->cidade->uuid,
                'bairro_uuid' => $this->bairro->uuid,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.numero', '123A')
            ->assertJsonPath('data.complemento', 'Fundos');

        $enderecoUuid = $response->json('data.uuid');

        $this->assertDatabaseHas('enderecos', [
            'uuid' => $enderecoUuid,
            'numero' => '123A',
            'complemento' => 'Fundos',
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/enderecos/' . $enderecoUuid, [
                'numero' => 'S/N',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.numero', 'S/N');
    }

    #[Test]
    public function user_cannot_create_endereco_with_bairro_from_another_cidade(): void
    {
        $this->grantPermission('enderecos', 'create');

        $otherCidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $this->estado->id,
            'name' => 'Santos',
            'is_active' => true,
        ]);

        $otherBairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $otherCidade->id,
            'name' => 'Gonzaga',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua das Flores, 100',
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $this->cidade->uuid,
                'bairro_uuid' => $otherBairro->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'LOCATION_CHAIN_MISMATCH');
    }

    #[Test]
    public function user_cannot_update_endereco_from_another_tenant(): void
    {
        $this->grantPermission('enderecos', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignEndereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'estado_id' => $this->estado->id,
            'cidade_id' => $this->cidade->id,
            'bairro_id' => $this->bairro->id,
            'logradouro' => 'Foreign Street',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/enderecos/' . $foreignEndereco->uuid, ['logradouro' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_endereco_from_another_tenant(): void
    {
        $this->grantPermission('enderecos', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignEndereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'estado_id' => $this->estado->id,
            'cidade_id' => $this->cidade->id,
            'bairro_id' => $this->bairro->id,
            'logradouro' => 'Foreign Street',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/enderecos/' . $foreignEndereco->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignEndereco);
    }

    #[Test]
    public function listing_can_be_sorted_and_filtered_by_cidade_and_bairro_name(): void
    {
        $this->grantPermission('enderecos', 'create');
        $this->grantPermission('enderecos', 'read');

        $outraCidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $this->estado->id,
            'name' => 'Amparo',
            'is_active' => true,
        ]);

        $outroBairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $outraCidade->id,
            'name' => 'Centro',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua A',
                'cep' => '13010-000',
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $this->cidade->uuid,
                'bairro_uuid' => $this->bairro->uuid,
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua B',
                'cep' => '13900-000',
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $outraCidade->uuid,
                'bairro_uuid' => $outroBairro->uuid,
            ])
            ->assertStatus(201);

        // sort_by=cidade_name (leftJoin) — Amparo < Campinas
        $sorted = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/enderecos?sort_by=cidade_name&sort_dir=asc')
            ->assertStatus(200);

        $sorted->assertJsonPath('data.0.logradouro', 'Rua B');
        $sorted->assertJsonPath('data.1.logradouro', 'Rua A');

        // filtro contains por bairro_name
        $byBairro = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/enderecos?bairro_name=Centro')
            ->assertStatus(200);

        $byBairro->assertJsonCount(1, 'data');
        $byBairro->assertJsonPath('data.0.logradouro', 'Rua B');

        // filtro contains por cep
        $byCep = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/enderecos?cep=13900')
            ->assertStatus(200);

        $byCep->assertJsonCount(1, 'data');
        $byCep->assertJsonPath('data.0.logradouro', 'Rua B');
    }
}
