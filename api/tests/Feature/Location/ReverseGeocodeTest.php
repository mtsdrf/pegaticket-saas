<?php

namespace Tests\Feature\Location;

use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReverseGeocodeTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected Estado $estado;
    protected Cidade $cidade;
    protected Bairro $bairro;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Reverse Geocode User',
            'email' => 'reverse-geocode@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'reverse-geocode@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->token = $login['access_token'];

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

    #[Test]
    public function matches_estado_cidade_and_bairro_exactly(): void
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

        $response = $this->auth()->getJson('/api/v1/location/reverse-geocode?lat=-22.9&lng=-47.0');

        $response->assertStatus(200)
            ->assertJsonPath('data.estado_uuid', $this->estado->uuid)
            ->assertJsonPath('data.cidade_uuid', $this->cidade->uuid)
            ->assertJsonPath('data.bairro_uuid', $this->bairro->uuid)
            ->assertJsonPath('data.logradouro', 'Rua das Flores')
            ->assertJsonPath('data.cep', '13024-000');
    }

    #[Test]
    public function matches_only_estado_and_cidade_when_bairro_does_not_match(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Rua Desconhecida',
                    'suburb' => 'Bairro Que Não Existe',
                    'city' => 'Campinas',
                    'state' => 'São Paulo',
                ],
            ], 200),
        ]);

        $response = $this->auth()->getJson('/api/v1/location/reverse-geocode?lat=-22.9&lng=-47.0');

        $response->assertStatus(200)
            ->assertJsonPath('data.estado_uuid', $this->estado->uuid)
            ->assertJsonPath('data.cidade_uuid', $this->cidade->uuid)
            ->assertJsonPath('data.bairro_uuid', null);
    }

    #[Test]
    public function returns_all_null_but_still_200_when_nothing_matches(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Some Road',
                    'city' => 'Nowhere City',
                    'state' => 'Nowhere State',
                ],
            ], 200),
        ]);

        $response = $this->auth()->getJson('/api/v1/location/reverse-geocode?lat=10&lng=10');

        $response->assertStatus(200)
            ->assertJsonPath('data.estado_uuid', null)
            ->assertJsonPath('data.cidade_uuid', null)
            ->assertJsonPath('data.bairro_uuid', null);
    }

    #[Test]
    public function two_calls_with_same_rounded_lat_lng_use_cache_only_one_http_call(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'city' => 'Campinas',
                    'state' => 'São Paulo',
                ],
            ], 200),
        ]);

        $this->auth()->getJson('/api/v1/location/reverse-geocode?lat=-22.90001&lng=-47.00001')
            ->assertStatus(200);

        $this->auth()->getJson('/api/v1/location/reverse-geocode?lat=-22.90001&lng=-47.00001')
            ->assertStatus(200);

        Http::assertSentCount(1);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->getJson('/api/v1/location/reverse-geocode?lat=-22.9&lng=-47.0')
            ->assertStatus(401);
    }

    #[Test]
    public function validates_lat_lng_range(): void
    {
        $this->auth()->getJson('/api/v1/location/reverse-geocode?lat=999&lng=-47.0')
            ->assertStatus(422);
    }
}
