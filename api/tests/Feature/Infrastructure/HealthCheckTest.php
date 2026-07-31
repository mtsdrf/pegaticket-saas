<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function returns_200_and_ok_status_when_everything_is_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => ['status' => 'ok'],
                    'storage' => ['status' => 'ok'],
                    'queue' => ['status' => 'ok'],
                    'payments' => ['status' => 'ok'],
                ],
            ]);
    }

    #[Test]
    public function returns_503_when_database_check_fails(): void
    {
        // Conexão inexistente força DB::connection()->getPdo() a lançar,
        // sem precisar mockar a facade DB inteira (o que quebraria as
        // outras checagens que também usam DB::table na mesma requisição).
        // Restaura o default original logo depois da requisição — RefreshDatabase
        // faz rollback da transação de teste no connection default no
        // tearDown, então deixar o override "vazar" quebra o teste seguinte.
        $originalDefault = config('database.default');

        config(['database.default' => 'health-check-broken']);
        config(['database.connections.health-check-broken' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'nonexistent',
            'username' => 'nonexistent',
            'password' => 'nonexistent',
        ]]);

        try {
            $response = $this->getJson('/api/v1/health');
        } finally {
            config(['database.default' => $originalDefault]);
        }

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'SERVICE_UNAVAILABLE')
            ->assertJsonPath('errors.database.status', 'failed');
    }

    #[Test]
    public function does_not_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
    }

    #[Test]
    public function returns_503_when_mercadopago_is_selected_without_required_credentials(): void
    {
        config([
            'services.payments.provider' => 'mercadopago',
            'services.mercadopago.environment' => 'test',
            'services.mercadopago.access_token' => '',
            'services.mercadopago.public_key' => '',
            'services.mercadopago.webhook_secret' => '',
        ]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJsonPath('code', 'SERVICE_UNAVAILABLE')
            ->assertJsonPath('errors.payments.status', 'failed');
    }
}
