<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MercadoPagoHealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.provider', 'mercadopago');
        Config::set('services.mercadopago.access_token', 'TEST-fake-token');
        Config::set('services.mercadopago.webhook_secret', 'fake-secret');
        Config::set('services.mercadopago.public_key', 'TEST-public-key');
        Config::set('services.mercadopago.environment', 'test');
    }

    #[Test]
    public function command_succeeds_when_mercadopago_accepts_the_token(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payment_methods' => Http::response([
                ['id' => 'pix'],
                ['id' => 'master'],
            ], 200),
        ]);

        $exitCode = Artisan::call('payments:mercadopago-health-check');

        $this->assertSame(0, $exitCode);
    }

    #[Test]
    public function command_fails_when_mercadopago_rejects_the_token(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payment_methods' => Http::response([
                'message' => 'invalid access token',
            ], 401),
        ]);

        $exitCode = Artisan::call('payments:mercadopago-health-check');

        $this->assertSame(1, $exitCode);
    }
}
