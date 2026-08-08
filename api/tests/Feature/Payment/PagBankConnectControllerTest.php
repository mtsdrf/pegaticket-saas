<?php

namespace Tests\Feature\Payment;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class PagBankConnectControllerTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-application-token');
        Config::set('services.pagbank.connect_client_id', 'fake-client-id');
        Config::set('services.pagbank.connect_client_secret', 'fake-client-secret');
        Config::set('services.pagbank.connect_redirect_uri', 'https://api.pegaticket.test/api/v1/pagbank-connect/callback');
        Config::set('app.frontend_url', 'https://app.pegaticket.test');

        $this->setUpTenantScopedUser('pagbank-connect-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function authorize_url_requires_financial_receiving_update_permission(): void
    {
        $this->auth()
            ->getJson('/api/v1/tenant-tools/pagbank-connect/authorize-url')
            ->assertStatus(403);
    }

    #[Test]
    public function authorize_url_rejects_legacy_tenant_settings_permission_alone(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->auth()
            ->getJson('/api/v1/tenant-tools/pagbank-connect/authorize-url')
            ->assertStatus(403);
    }

    #[Test]
    public function authorize_url_returns_the_pagbank_connect_url_when_authorized(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        $response = $this->auth()
            ->getJson('/api/v1/tenant-tools/pagbank-connect/authorize-url')
            ->assertStatus(200);

        $url = $response->json('data.authorize_url');
        $this->assertStringStartsWith('https://connect.sandbox.pagbank.com.br/oauth2/authorize?', $url);

        $this->assertDatabaseHas('tenant_pagbank_connections', [
            'tenant_id' => $this->tenant->id,
            'status' => TenantPagBankConnection::STATUS_PENDING_CONNECTION,
        ]);
    }

    #[Test]
    public function status_requires_tenant_settings_read_permission(): void
    {
        $this->auth()
            ->getJson('/api/v1/tenant-tools/pagbank-connect/status')
            ->assertStatus(403);
    }

    #[Test]
    public function status_returns_null_data_when_tenant_has_no_connection_yet(): void
    {
        $this->grantPermission('financial_receiving', 'read');

        $this->auth()
            ->getJson('/api/v1/tenant-tools/pagbank-connect/status')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function status_never_exposes_a_connection_belonging_to_another_tenant(): void
    {
        $this->grantPermission('financial_receiving', 'read');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'provider' => 'pagbank',
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_OTHER',
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);

        $this->auth()
            ->getJson('/api/v1/tenant-tools/pagbank-connect/status')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function disconnect_requires_tenant_settings_update_permission(): void
    {
        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/disconnect')
            ->assertStatus(403);
    }

    #[Test]
    public function disconnect_returns_error_when_tenant_has_no_connection(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/disconnect')
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAGBANK_CONNECT_NOT_CONNECTED');
    }

    #[Test]
    public function disconnect_clears_the_tenant_connection(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_MINE',
            'access_token_encrypted' => 'ACCESS',
            'refresh_token_encrypted' => 'REFRESH',
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/revoke' => Http::response([], 200),
        ]);

        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/disconnect')
            ->assertStatus(200);

        $this->assertDatabaseHas('tenant_pagbank_connections', [
            'tenant_id' => $this->tenant->id,
            'status' => TenantPagBankConnection::STATUS_DISABLED,
        ]);
    }

    #[Test]
    public function callback_is_public_and_only_accepts_a_state_it_generated_itself(): void
    {
        // Sem Authorization header — a rota é 100% pública.
        $response = $this->getJson('/api/v1/pagbank-connect/callback?code=any-code&state=state-nobody-generated');

        $response->assertRedirectContains('pagbank_connect=error');
    }

    #[Test]
    public function callback_succeeds_and_redirects_for_a_state_generated_by_this_application(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        $this->auth()
            ->getJson('/api/v1/tenant-tools/pagbank-connect/authorize-url')
            ->assertStatus(200);

        $connection = TenantPagBankConnection::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $state = $connection->connect_state;

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/token' => Http::response([
                'access_token' => 'ACCESS-TOKEN',
                'refresh_token' => 'REFRESH-TOKEN',
                'expires_in' => '14400',
                'scope' => 'payments.read',
                'account_id' => 'ACCO_CALLBACK',
            ], 201),
        ]);

        $response = $this->getJson('/api/v1/pagbank-connect/callback?code=valid-code&state='.$state);

        $response->assertRedirectContains('pagbank_connect=success');

        $this->assertDatabaseHas('tenant_pagbank_connections', [
            'tenant_id' => $this->tenant->id,
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_CALLBACK',
        ]);
    }
}
