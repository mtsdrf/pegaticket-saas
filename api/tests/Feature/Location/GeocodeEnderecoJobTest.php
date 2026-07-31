<?php

namespace Tests\Feature\Location;

use App\Jobs\Location\GeocodeEnderecoJob;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeocodeEnderecoJobTest extends TestCase
{
    use RefreshDatabase;

    protected ?Estado $estado = null;
    protected ?Cidade $cidade = null;
    protected ?Bairro $bairro = null;

    protected function makeEndereco(array $overrides = []): Endereco
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        // Cadeia geográfica compartilhada entre chamadas (uf é unique em
        // estados) — só é criada uma vez por teste, reutilizada quando o
        // teste cria mais de um Endereco (ex.: cache).
        if (!$this->estado) {
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

        return Endereco::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'estado_id' => $this->estado->id,
            'cidade_id' => $this->cidade->id,
            'bairro_id' => $this->bairro->id,
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'cep' => '13000-000',
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function job_updates_lat_lng_and_status_when_nominatim_finds_result(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-22.9068', 'lon' => '-47.0626'],
            ], 200),
        ]);

        $endereco = $this->makeEndereco();

        (new GeocodeEnderecoJob($endereco->id))->handle();

        $endereco->refresh();

        $this->assertEquals(-22.9068, $endereco->lat);
        $this->assertEquals(-47.0626, $endereco->lng);
        $this->assertEquals('success', $endereco->geocode_status);
        $this->assertNotNull($endereco->geocoded_at);
    }

    #[Test]
    public function job_marks_failed_when_nominatim_returns_empty(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $endereco = $this->makeEndereco();

        (new GeocodeEnderecoJob($endereco->id))->handle();

        $endereco->refresh();

        $this->assertNull($endereco->lat);
        $this->assertNull($endereco->lng);
        $this->assertEquals('failed', $endereco->geocode_status);
        $this->assertNotNull($endereco->geocoded_at);
    }

    #[Test]
    public function job_does_not_call_http_twice_for_same_query_due_to_cache(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-22.9068', 'lon' => '-47.0626'],
            ], 200),
        ]);

        $enderecoA = $this->makeEndereco();
        $enderecoB = $this->makeEndereco(['uuid' => (string) Str::uuid()]);

        (new GeocodeEnderecoJob($enderecoA->id))->handle();
        (new GeocodeEnderecoJob($enderecoB->id))->handle();

        Http::assertSentCount(1);

        $enderecoB->refresh();
        $this->assertEquals('success', $enderecoB->geocode_status);
    }

    #[Test]
    public function job_returns_early_when_endereco_no_longer_exists(): void
    {
        Http::fake();

        (new GeocodeEnderecoJob(999999))->handle();

        Http::assertNothingSent();
    }

    /**
     * $tries esgotadas por EXCEÇÃO (rede/429) é diferente de "Nominatim não
     * achou o endereço" — não pode virar 'failed' permanente, senão um
     * problema transitório (rate limit) marca endereços geocodificáveis
     * como definitivamente não localizáveis. Endereço fica 'pending' pra
     * ser tentado de novo num backfill futuro.
     */
    #[Test]
    public function failed_hook_does_not_mark_endereco_as_failed(): void
    {
        $endereco = $this->makeEndereco();

        (new GeocodeEnderecoJob($endereco->id))->failed(new \Exception('network error'));

        $endereco->refresh();

        $this->assertEquals('pending', $endereco->geocode_status);
        $this->assertNull($endereco->geocoded_at);
    }
}
