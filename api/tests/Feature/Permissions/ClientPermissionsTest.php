<?php

namespace Tests\Feature\Permissions;

use App\Models\Client\Client;
use App\Models\Client\ClientCategory;
use App\Models\Client\DiaIdeal;
use App\Models\Client\PeriodoIdeal;
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

class ClientPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected Estado $estado;
    protected Cidade $cidade;
    protected Bairro $bairro;

    protected function setUp(): void
    {
        parent::setUp();

        // ClientService::create() sempre cria um Endereco novo, que dispara
        // GeocodeEnderecoJob síncrono em testing — nenhum teste aqui
        // verifica lat/lng, só precisa não bater na API real.
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $this->setUpTenantScopedUser('client-user@test.com');

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

    protected function basePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'cpf_cnpj' => '12345678901',
            'estado_uuid' => $this->estado->uuid,
            'cidade_uuid' => $this->cidade->uuid,
            'bairro_uuid' => $this->bairro->uuid,
            'logradouro' => 'Rua das Flores, 100',
        ], $overrides);
    }

    #[Test]
    public function user_without_permission_cannot_list_clients(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_clients(): void
    {
        $this->grantPermission('clients', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_create_permission_can_create_client_with_inline_endereco(): void
    {
        $this->grantPermission('clients', 'create');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload())
            ->assertStatus(201);

        $response->assertJsonPath('data.name', 'John Doe');
        $response->assertJsonPath('data.endereco.logradouro', 'Rua das Flores, 100');
        $response->assertJsonPath('data.endereco.bairro_name', 'Cambuí');

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('enderecos', 1);
    }

    #[Test]
    public function user_with_create_permission_can_create_client_with_alphanumeric_cnpj(): void
    {
        $this->grantPermission('clients', 'create');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload([
                'cpf_cnpj' => 'AA.345.678/000A-29',
            ]))
            ->assertStatus(201);

        $response->assertJsonPath('data.cpf_cnpj', 'AA345678000A29');
        $this->assertDatabaseHas('clients', [
            'uuid' => $response->json('data.uuid'),
            'cpf_cnpj' => 'AA345678000A29',
        ]);
    }

    /**
     * Fase 8 (migração de dados reais): legado guardava numero/complemento
     * no cliente, não no endereço. No fluxo de Cliente (endereço inline),
     * os dois campos são opcionais e propagam pro Endereco criado/atualizado.
     */
    #[Test]
    public function numero_and_complemento_propagate_to_inline_endereco(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload([
                'numero' => '456',
                'complemento' => 'Bloco B, apto 12',
            ]))
            ->assertStatus(201);

        $response->assertJsonPath('data.endereco.numero', '456');
        $response->assertJsonPath('data.endereco.complemento', 'Bloco B, apto 12');

        $clientUuid = $response->json('data.uuid');

        $this->assertDatabaseHas('enderecos', [
            'uuid' => $response->json('data.endereco.uuid'),
            'numero' => '456',
            'complemento' => 'Bloco B, apto 12',
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/clients/' . $clientUuid, ['numero' => '456B']);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.endereco.numero', '456B');
    }

    /**
     * lat/lng manual (GPS) no cliente bypassa o Nominatim: repassado
     * Client -> CreateEnderecoDTO/UpdateEnderecoDTO -> EnderecoService,
     * mesma trilha do endpoint standalone de Endereco.
     */
    #[Test]
    public function manual_lat_lng_propagates_to_inline_endereco_and_skips_geocoding(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload([
                'lat' => -22.9068,
                'lng' => -43.1729,
            ]))
            ->assertStatus(201);

        $response->assertJsonPath('data.endereco.lat', -22.9068);
        $response->assertJsonPath('data.endereco.lng', -43.1729);
        $response->assertJsonPath('data.endereco.geocode_status', 'manual');

        Http::assertNothingSent();

        $clientUuid = $response->json('data.uuid');

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/clients/' . $clientUuid, ['lat' => -23.5505, 'lng' => -46.6333]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.endereco.lat', -23.5505)
            ->assertJsonPath('data.endereco.lng', -46.6333)
            ->assertJsonPath('data.endereco.geocode_status', 'manual');
    }

    #[Test]
    public function creating_client_without_name_or_endereco_fails_validation(): void
    {
        $this->grantPermission('clients', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name', 'estado_uuid', 'cidade_uuid', 'bairro_uuid', 'logradouro']]);
    }

    #[Test]
    public function is_trusted_false_is_actually_persisted(): void
    {
        $this->grantPermission('clients', 'create');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload(['is_trusted' => false]))
            ->assertStatus(201);

        $response->assertJsonPath('data.is_trusted', false);

        $this->assertDatabaseHas('clients', [
            'uuid' => $response->json('data.uuid'),
            'is_trusted' => 0,
        ]);
    }

    #[Test]
    public function partial_update_of_phone_does_not_touch_endereco(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'update');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload())
            ->assertStatus(201)
            ->json('data');

        $originalEnderecoUuid = $created['endereco']['uuid'];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/clients/' . $created['uuid'], [
                'phone_primary' => '11999998888',
            ])
            ->assertStatus(200);

        $response->assertJsonPath('data.phone_primary', '11999998888');
        $response->assertJsonPath('data.endereco.uuid', $originalEnderecoUuid);
        $response->assertJsonPath('data.endereco.logradouro', 'Rua das Flores, 100');
    }

    #[Test]
    public function user_can_sync_client_categories(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'update');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload())
            ->assertStatus(201)
            ->json('data');

        $category = ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'VIP',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients/' . $created['uuid'] . '/categories/sync', [
                'category_uuids' => [$category->uuid],
            ])
            ->assertStatus(200);

        $response->assertJsonPath('data.categories.0.name', 'VIP');
    }

    #[Test]
    public function user_cannot_sync_categories_from_another_tenant(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'update');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload())
            ->assertStatus(201)
            ->json('data');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignCategory = ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign VIP',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients/' . $created['uuid'] . '/categories/sync', [
                'category_uuids' => [$foreignCategory->uuid],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function user_with_read_permission_can_show_own_client(): void
    {
        $this->grantPermission('clients', 'read');

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'estado_id' => $this->estado->id,
            'cidade_id' => $this->cidade->id,
            'bairro_id' => $this->bairro->id,
            'logradouro' => 'Rua das Flores, 100',
            'is_active' => true,
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $endereco->id,
            'name' => 'John Doe',
            'is_trusted' => true,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients/' . $client->uuid)
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $client->uuid)
            ->assertJsonPath('data.endereco.estado_uuid', $this->estado->uuid);
    }

    #[Test]
    public function user_cannot_show_client_from_another_tenant(): void
    {
        $this->grantPermission('clients', 'read');

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

        $foreignClient = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'endereco_id' => $foreignEndereco->id,
            'name' => 'Foreign Client',
            'is_trusted' => true,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients/' . $foreignClient->uuid)
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_update_client_from_another_tenant(): void
    {
        $this->grantPermission('clients', 'update');

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

        $foreignClient = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'endereco_id' => $foreignEndereco->id,
            'name' => 'Foreign Client',
            'is_trusted' => true,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/clients/' . $foreignClient->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_client_from_another_tenant(): void
    {
        $this->grantPermission('clients', 'delete');

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

        $foreignClient = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'endereco_id' => $foreignEndereco->id,
            'name' => 'Foreign Client',
            'is_trusted' => true,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/clients/' . $foreignClient->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignClient);
    }

    #[Test]
    public function user_can_delete_own_client(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'delete');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload())
            ->assertStatus(201)
            ->json('data');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/clients/' . $created['uuid'])
            ->assertStatus(204);

        $this->assertSoftDeleted('clients', ['uuid' => $created['uuid']]);
    }

    #[Test]
    public function listing_can_be_filtered_by_name(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload(['name' => 'Alice Wonderland']))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload(['name' => 'Bob Builder']))
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?name=Alice')
            ->assertStatus(200);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alice Wonderland');
    }

    #[Test]
    public function listing_can_be_sorted_and_filtered_by_cidade_name(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'read');

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
            ->postJson('/api/v1/clients', $this->basePayload(['name' => 'Cliente Campinas']))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload([
                'name' => 'Cliente Amparo',
                'cidade_uuid' => $outraCidade->uuid,
                'bairro_uuid' => $outroBairro->uuid,
            ]))
            ->assertStatus(201);

        $asc = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?sort_by=cidade_name&sort_dir=asc')
            ->assertStatus(200);

        $asc->assertJsonPath('data.0.name', 'Cliente Amparo');
        $asc->assertJsonPath('data.1.name', 'Cliente Campinas');

        $desc = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?sort_by=cidade_name&sort_dir=desc')
            ->assertStatus(200);

        $desc->assertJsonPath('data.0.name', 'Cliente Campinas');

        $filtered = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?cidade_name=Amparo')
            ->assertStatus(200);

        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.name', 'Cliente Amparo');
    }

    #[Test]
    public function listing_can_be_searched_globally_with_q_across_name_phone_and_cidade(): void
    {
        $this->grantPermission('clients', 'create');
        $this->grantPermission('clients', 'read');

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
            ->postJson('/api/v1/clients', $this->basePayload([
                'name' => 'Alice Wonderland',
                'phone_primary' => '11911112222',
            ]))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload([
                'name' => 'Bob Builder',
                'phone_primary' => '11955559999',
                'cidade_uuid' => $outraCidade->uuid,
                'bairro_uuid' => $outroBairro->uuid,
            ]))
            ->assertStatus(201);

        // match por name
        $byName = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?q=Alice')
            ->assertStatus(200);
        $byName->assertJsonCount(1, 'data');
        $byName->assertJsonPath('data.0.name', 'Alice Wonderland');

        // match por phone_primary
        $byPhone = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?q=5555')
            ->assertStatus(200);
        $byPhone->assertJsonCount(1, 'data');
        $byPhone->assertJsonPath('data.0.name', 'Bob Builder');

        // match por cidade_name (via relação endereco.cidade)
        $byCidade = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?q=Amparo')
            ->assertStatus(200);
        $byCidade->assertJsonCount(1, 'data');
        $byCidade->assertJsonPath('data.0.name', 'Bob Builder');

        // sem match nenhum
        $noMatch = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/clients?q=Nonexistent')
            ->assertStatus(200);
        $noMatch->assertJsonCount(0, 'data');
    }

    #[Test]
    public function client_can_be_created_with_dia_ideal_and_periodo_ideal(): void
    {
        $this->grantPermission('clients', 'create');

        $diaIdeal = DiaIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Segunda-feira',
            'is_active' => true,
        ]);

        $periodoIdeal = PeriodoIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Manhã',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/clients', $this->basePayload([
                'dia_ideal_uuid' => $diaIdeal->uuid,
                'periodo_ideal_uuid' => $periodoIdeal->uuid,
            ]))
            ->assertStatus(201);

        $response->assertJsonPath('data.dia_ideal.name', 'Segunda-feira');
        $response->assertJsonPath('data.periodo_ideal.name', 'Manhã');
    }
}
