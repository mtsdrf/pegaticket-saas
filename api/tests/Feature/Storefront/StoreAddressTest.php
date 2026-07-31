<?php

namespace Tests\Feature\Storefront;

use App\Jobs\Location\GeocodeEnderecoJob;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * GET/PUT /store-settings/address (reforma da loja) — endereço próprio da
 * empresa reaproveitando o model genérico Endereco via tenants.endereco_id.
 * Ver App\Services\Storefront\StoreAddressService.
 */
class StoreAddressTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected Estado $estado;
    protected Cidade $cidade;
    protected Bairro $bairro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('store-address-user@test.com');

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

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'complemento' => 'Sala 4',
            'cep' => '13000-000',
            'is_active' => true,
            'estado_uuid' => $this->estado->uuid,
            'cidade_uuid' => $this->cidade->uuid,
            'bairro_uuid' => $this->bairro->uuid,
        ], $overrides);
    }

    #[Test]
    public function put_creates_address_and_links_it_to_the_tenant(): void
    {
        Queue::fake();

        $this->grantPermission('storefront', 'update');

        $response = $this->auth()->putJson('/api/v1/store-settings/address', $this->validPayload())
            ->assertStatus(200);

        $uuid = $response->json('data.uuid');
        $endereco = Endereco::where('uuid', $uuid)->firstOrFail();

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'endereco_id' => $endereco->id,
        ]);

        Queue::assertPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $endereco->id);
    }

    #[Test]
    public function put_updates_existing_address_without_creating_a_new_one(): void
    {
        Queue::fake();

        $this->grantPermission('storefront', 'update');

        $this->auth()->putJson('/api/v1/store-settings/address', $this->validPayload())
            ->assertStatus(200);

        $this->tenant->refresh();
        $originalEnderecoId = $this->tenant->endereco_id;
        $this->assertNotNull($originalEnderecoId);

        $this->auth()->putJson('/api/v1/store-settings/address', $this->validPayload([
            'logradouro' => 'Avenida Nova',
        ]))->assertStatus(200);

        $this->tenant->refresh();

        // Mesmo endereço (atualizado), nunca um novo registro.
        $this->assertEquals($originalEnderecoId, $this->tenant->endereco_id);
        $this->assertDatabaseCount('enderecos', 1);
        $this->assertDatabaseHas('enderecos', [
            'id' => $originalEnderecoId,
            'logradouro' => 'Avenida Nova',
        ]);
    }

    #[Test]
    public function get_returns_null_when_tenant_has_no_address(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->getJson('/api/v1/store-settings/address')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function get_returns_the_address_after_it_is_configured(): void
    {
        Queue::fake();

        $this->grantPermission('storefront', 'update');

        $this->auth()->putJson('/api/v1/store-settings/address', $this->validPayload())
            ->assertStatus(200);

        $this->auth()->getJson('/api/v1/store-settings/address')
            ->assertStatus(200)
            ->assertJsonPath('data.logradouro', 'Rua das Flores')
            ->assertJsonPath('data.cidade_name', 'Campinas')
            ->assertJsonPath('data.bairro_name', 'Cambuí');
    }

    #[Test]
    public function user_without_permission_cannot_view_or_update(): void
    {
        $this->auth()->getJson('/api/v1/store-settings/address')->assertStatus(403);
        $this->auth()->putJson('/api/v1/store-settings/address', $this->validPayload())
            ->assertStatus(403);
    }
}
