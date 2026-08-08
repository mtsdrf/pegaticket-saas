<?php

namespace Tests\Feature\Payment;

use App\Exceptions\Payment\PagBankConnectException;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use App\Services\Payment\PagBankConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fluxo OAuth PagBank Connect (roadmap fase R2). Client HTTP mockado
 * (Http::fake) — nenhuma chamada de rede real acontece em teste, seguindo
 * o mesmo padrão de PagBankSalePaymentTest.
 */
class PagBankConnectServiceTest extends TestCase
{
    use RefreshDatabase;

    private PagBankConnectService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-application-token');
        Config::set('services.pagbank.connect_client_id', 'fake-client-id');
        Config::set('services.pagbank.connect_client_secret', 'fake-client-secret');
        Config::set('services.pagbank.connect_redirect_uri', 'https://api.pegaticket.test/api/v1/pagbank-connect/callback');
        Config::set('app.frontend_url', 'https://app.pegaticket.test');

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Connect',
            'slug' => 'tenant-connect-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->service = app(PagBankConnectService::class);
    }

    #[Test]
    public function it_builds_the_authorization_url_with_all_required_params(): void
    {
        $url = $this->service->buildAuthorizationUrl($this->tenant->id);

        $this->assertStringStartsWith('https://connect.sandbox.pagbank.com.br/oauth2/authorize?', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=fake-client-id', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('scope=payments.read%2Bpayments.create%2Bpayments.refund%2Baccounts.read', $url);
        $this->assertStringContainsString('state=', $url);

        $connection = TenantPagBankConnection::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($connection);
        $this->assertSame(TenantPagBankConnection::STATUS_PENDING_CONNECTION, $connection->status);
        $this->assertNotEmpty($connection->connect_state);
    }

    #[Test]
    public function callback_with_an_unknown_state_is_rejected(): void
    {
        $this->service->buildAuthorizationUrl($this->tenant->id);

        $this->expectException(PagBankConnectException::class);
        $this->expectExceptionMessage('pagbank_connect.invalid_state');

        $this->service->handleCallback('some-code', 'a-state-nobody-generated');
    }

    #[Test]
    public function callback_with_the_correct_state_exchanges_the_code_and_persists_the_connection(): void
    {
        $this->service->buildAuthorizationUrl($this->tenant->id);
        $connection = TenantPagBankConnection::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $state = $connection->connect_state;

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/token' => Http::response([
                'token_type' => 'bearer',
                'access_token' => 'ACCESS-TOKEN-XYZ',
                'expires_in' => '14400',
                'refresh_token' => 'REFRESH-TOKEN-XYZ',
                'scope' => 'payments.read payments.create',
                'account_id' => 'ACCO_ABC123',
            ], 201),
        ]);

        $result = $this->service->handleCallback('auth-code-123', $state);

        $this->assertSame(TenantPagBankConnection::STATUS_ENABLED, $result->status);
        $this->assertSame('ACCO_ABC123', $result->account_id);
        $this->assertNull($result->connect_state);
        $this->assertNotNull($result->connected_at);
        $this->assertNotNull($result->token_expires_at);

        $fresh = TenantPagBankConnection::query()->findOrFail($result->id);
        $this->assertSame('ACCESS-TOKEN-XYZ', $fresh->access_token_encrypted);
        $this->assertSame('REFRESH-TOKEN-XYZ', $fresh->refresh_token_encrypted);

        // Token cru nunca gravado em texto plano no banco (cast encrypted).
        $rawColumn = \DB::table('tenant_pagbank_connections')->where('id', $result->id)->value('access_token_encrypted');
        $this->assertNotSame('ACCESS-TOKEN-XYZ', $rawColumn);
    }

    #[Test]
    public function callback_reusing_the_same_code_twice_fails_the_second_time_because_the_state_is_already_consumed(): void
    {
        $this->service->buildAuthorizationUrl($this->tenant->id);
        $connection = TenantPagBankConnection::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $state = $connection->connect_state;

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/token' => Http::response([
                'access_token' => 'ACCESS-1',
                'refresh_token' => 'REFRESH-1',
                'expires_in' => '14400',
                'scope' => 'payments.read',
                'account_id' => 'ACCO_1',
            ], 201),
        ]);

        $this->service->handleCallback('auth-code-123', $state);

        $this->expectException(PagBankConnectException::class);
        $this->service->handleCallback('auth-code-123', $state);
    }

    #[Test]
    public function refresh_token_if_needed_renews_the_token_when_close_to_expiring(): void
    {
        $connection = TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_1',
            'access_token_encrypted' => 'OLD-ACCESS',
            'refresh_token_encrypted' => 'OLD-REFRESH',
            'token_expires_at' => now()->addMinute(),
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/refresh' => Http::response([
                'access_token' => 'NEW-ACCESS',
                'refresh_token' => 'NEW-REFRESH',
                'expires_in' => '14400',
            ], 201),
        ]);

        $result = $this->service->refreshTokenIfNeeded($connection);

        $this->assertSame('NEW-ACCESS', $result->access_token_encrypted);
        $this->assertSame('NEW-REFRESH', $result->refresh_token_encrypted);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/oauth2/refresh'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'OLD-REFRESH';
        });
    }

    #[Test]
    public function refresh_token_if_needed_does_nothing_when_token_is_not_close_to_expiring(): void
    {
        $connection = TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_1',
            'access_token_encrypted' => 'CURRENT-ACCESS',
            'refresh_token_encrypted' => 'CURRENT-REFRESH',
            'token_expires_at' => now()->addDays(5),
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);

        Http::fake();

        $result = $this->service->refreshTokenIfNeeded($connection);

        $this->assertSame('CURRENT-ACCESS', $result->access_token_encrypted);
        Http::assertNothingSent();
    }

    #[Test]
    public function disconnect_revokes_remotely_and_clears_local_tokens(): void
    {
        $connection = TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_1',
            'access_token_encrypted' => 'ACCESS-TO-REVOKE',
            'refresh_token_encrypted' => 'REFRESH-TO-REVOKE',
            'token_expires_at' => now()->addDays(5),
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/revoke' => Http::response([], 200),
        ]);

        $this->service->disconnect($connection);

        $fresh = TenantPagBankConnection::query()->findOrFail($connection->id);
        $this->assertSame(TenantPagBankConnection::STATUS_DISABLED, $fresh->status);
        $this->assertNull($fresh->access_token_encrypted);
        $this->assertNull($fresh->refresh_token_encrypted);
        $this->assertNotNull($fresh->disconnected_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/oauth2/revoke'
                && $request['token'] === 'ACCESS-TO-REVOKE';
        });
    }

    #[Test]
    public function disconnect_still_clears_local_state_even_if_the_remote_revoke_call_fails(): void
    {
        $connection = TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_1',
            'access_token_encrypted' => 'ACCESS-TO-REVOKE',
            'refresh_token_encrypted' => 'REFRESH-TO-REVOKE',
            'token_expires_at' => now()->addDays(5),
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/revoke' => Http::response(['error' => 'server_error'], 500),
        ]);

        $this->service->disconnect($connection);

        $fresh = TenantPagBankConnection::query()->findOrFail($connection->id);
        $this->assertSame(TenantPagBankConnection::STATUS_DISABLED, $fresh->status);
        $this->assertNull($fresh->access_token_encrypted);
    }
}
