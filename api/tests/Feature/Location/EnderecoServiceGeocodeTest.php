<?php

namespace Tests\Feature\Location;

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

class EnderecoServiceGeocodeTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected Estado $estado;
    protected Cidade $cidade;
    protected Bairro $bairro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('endereco-geocode-user@test.com');

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

    protected function seedEndereco(array $overrides = []): Endereco
    {
        return Endereco::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'estado_id' => $this->estado->id,
            'cidade_id' => $this->cidade->id,
            'bairro_id' => $this->bairro->id,
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'cep' => '13000-000',
            'is_active' => true,
            'lat' => -22.9,
            'lng' => -47.0,
            'geocode_status' => 'success',
            'geocoded_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function create_dispatches_geocode_job(): void
    {
        Queue::fake();

        $this->grantPermission('enderecos', 'create');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua Nova',
                'numero' => '10',
                'cep' => '13100-000',
                'is_active' => true,
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $this->cidade->uuid,
                'bairro_uuid' => $this->bairro->uuid,
            ])
            ->assertStatus(201);

        $uuid = $response->json('data.uuid');
        $endereco = Endereco::where('uuid', $uuid)->firstOrFail();

        Queue::assertPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $endereco->id);
    }

    #[Test]
    public function update_dispatches_job_and_resets_coordinates_when_logradouro_changes(): void
    {
        Queue::fake();

        $this->grantPermission('enderecos', 'update');

        $endereco = $this->seedEndereco();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/enderecos/' . $endereco->uuid, [
                'logradouro' => 'Rua Alterada',
            ])
            ->assertStatus(200);

        $endereco->refresh();

        $this->assertEquals('Rua Alterada', $endereco->logradouro);
        $this->assertNull($endereco->lat);
        $this->assertNull($endereco->lng);
        $this->assertEquals('pending', $endereco->geocode_status);

        Queue::assertPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $endereco->id);
    }

    #[Test]
    public function update_does_not_dispatch_job_when_only_is_active_changes(): void
    {
        Queue::fake();

        $this->grantPermission('enderecos', 'update');

        $endereco = $this->seedEndereco();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/enderecos/' . $endereco->uuid, [
                'is_active' => false,
            ])
            ->assertStatus(200);

        $endereco->refresh();

        $this->assertFalse((bool) $endereco->is_active);
        $this->assertEquals(-22.9, $endereco->lat);
        $this->assertEquals(-47.0, $endereco->lng);
        $this->assertEquals('success', $endereco->geocode_status);

        Queue::assertNotPushed(GeocodeEnderecoJob::class);
    }

    #[Test]
    public function create_with_manual_lat_lng_bypasses_geocode_job(): void
    {
        Queue::fake();

        $this->grantPermission('enderecos', 'create');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua Nova',
                'numero' => '10',
                'cep' => '13100-000',
                'is_active' => true,
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $this->cidade->uuid,
                'bairro_uuid' => $this->bairro->uuid,
                'lat' => -22.912,
                'lng' => -47.061,
            ])
            ->assertStatus(201);

        $uuid = $response->json('data.uuid');
        $endereco = Endereco::where('uuid', $uuid)->firstOrFail();

        $this->assertEquals(-22.912, $endereco->lat);
        $this->assertEquals(-47.061, $endereco->lng);
        $this->assertEquals('manual', $endereco->geocode_status);
        $this->assertNotNull($endereco->geocoded_at);

        Queue::assertNotPushed(GeocodeEnderecoJob::class);
    }

    #[Test]
    public function create_with_only_lat_ignores_it_and_still_geocodes(): void
    {
        Queue::fake();

        $this->grantPermission('enderecos', 'create');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/enderecos', [
                'logradouro' => 'Rua Nova',
                'numero' => '10',
                'cep' => '13100-000',
                'is_active' => true,
                'estado_uuid' => $this->estado->uuid,
                'cidade_uuid' => $this->cidade->uuid,
                'bairro_uuid' => $this->bairro->uuid,
                'lat' => -22.912,
            ])
            ->assertStatus(201);

        $uuid = $response->json('data.uuid');
        $endereco = Endereco::where('uuid', $uuid)->firstOrFail();

        $this->assertNull($endereco->lat);
        $this->assertNull($endereco->lng);
        $this->assertNotEquals('manual', $endereco->geocode_status);

        Queue::assertPushed(GeocodeEnderecoJob::class);
    }

    #[Test]
    public function update_with_manual_lat_lng_bypasses_geocode_job_even_when_logradouro_also_changes(): void
    {
        Queue::fake();

        $this->grantPermission('enderecos', 'update');

        $endereco = $this->seedEndereco();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/enderecos/' . $endereco->uuid, [
                'logradouro' => 'Rua Alterada',
                'lat' => -23.5,
                'lng' => -46.6,
            ])
            ->assertStatus(200);

        $endereco->refresh();

        $this->assertEquals('Rua Alterada', $endereco->logradouro);
        $this->assertEquals(-23.5, $endereco->lat);
        $this->assertEquals(-46.6, $endereco->lng);
        $this->assertEquals('manual', $endereco->geocode_status);

        Queue::assertNotPushed(GeocodeEnderecoJob::class);
    }
}
