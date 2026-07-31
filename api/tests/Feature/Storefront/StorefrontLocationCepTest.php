<?php

namespace Tests\Feature\Storefront;

use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CEP (ViaCEP) + reverse-geocode públicos do endereço do checkout da loja
 * (`/loja/localizacoes/cep/{cep}` e `/loja/localizacoes/reverse-geocode`) —
 * 100% público (sem jwt/tenant/perm), mesmo espírito das outras rotas de
 * `/loja/localizacoes/*`. `LocalAddressMatcher::matchByEstadoUf()` casa
 * pela sigla do estado (ViaCEP nunca devolve o nome completo).
 */
class StorefrontLocationCepTest extends TestCase
{
    use RefreshDatabase;

    protected Estado $estado;
    protected Cidade $cidade;
    protected Bairro $bairro;

    protected function setUp(): void
    {
        parent::setUp();

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
    public function cep_endpoint_matches_estado_cidade_and_bairro_by_uf(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'cep' => '13024-000',
                'logradouro' => 'Rua das Flores',
                'bairro' => 'Cambuí',
                'localidade' => 'Campinas',
                'uf' => 'SP',
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/loja/localizacoes/cep/13024000');

        $response->assertStatus(200)
            ->assertJsonPath('data.estado_uuid', $this->estado->uuid)
            ->assertJsonPath('data.cidade_uuid', $this->cidade->uuid)
            ->assertJsonPath('data.bairro_uuid', $this->bairro->uuid)
            ->assertJsonPath('data.logradouro', 'Rua das Flores')
            ->assertJsonPath('data.cep', '13024-000');
    }

    #[Test]
    public function cep_endpoint_accepts_formatted_cep_with_dash(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'cep' => '13024-000',
                'logradouro' => 'Rua das Flores',
                'bairro' => 'Cambuí',
                'localidade' => 'Campinas',
                'uf' => 'SP',
            ], 200),
        ]);

        $this->getJson('/api/v1/loja/localizacoes/cep/13024-000')
            ->assertStatus(200)
            ->assertJsonPath('data.estado_uuid', $this->estado->uuid);
    }

    #[Test]
    public function cep_endpoint_returns_404_when_viacep_reports_not_found(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true], 200),
        ]);

        $this->getJson('/api/v1/loja/localizacoes/cep/00000000')
            ->assertStatus(404)
            ->assertJsonPath('code', 'CEP_NOT_FOUND');
    }

    #[Test]
    public function cep_endpoint_returns_404_for_malformed_cep_without_calling_external_api(): void
    {
        Http::fake();

        $this->getJson('/api/v1/loja/localizacoes/cep/123')
            ->assertStatus(404)
            ->assertJsonPath('code', 'CEP_NOT_FOUND');

        Http::assertNothingSent();
    }

    #[Test]
    public function reverse_geocode_endpoint_works_without_any_authentication(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Rua das Flores',
                    'suburb' => 'Cambuí',
                    'city' => 'Campinas',
                    'state' => 'São Paulo',
                    'postcode' => '13024-000',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/loja/localizacoes/reverse-geocode?lat=-22.9&lng=-47.0');

        $response->assertStatus(200)
            ->assertJsonPath('data.estado_uuid', $this->estado->uuid)
            ->assertJsonPath('data.bairro_uuid', $this->bairro->uuid);
    }
}
