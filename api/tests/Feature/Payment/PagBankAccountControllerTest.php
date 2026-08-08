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

class PagBankAccountControllerTest extends TestCase
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

        $this->setUpTenantScopedUser('pagbank-account-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function validPayload(): array
    {
        return [
            'person_type' => 'pf',
            'email' => 'seller@test.com',
            'terms_accepted_at' => now()->toIso8601String(),
            'person' => [
                'name' => 'Maria da Silva',
                'birth_date' => '1994-07-03',
                'mother_name' => 'Joana da Silva',
                'tax_id' => '111.444.777-35',
                'address' => [
                    'street' => 'Rua das Flores',
                    'number' => '100',
                    'locality' => 'Centro',
                    'city' => 'São Paulo',
                    'region_code' => 'SP',
                    'postal_code' => '01000-000',
                    'country' => 'BRA',
                ],
                'phone' => ['country' => '55', 'area' => '11', 'number' => '981111111'],
            ],
        ];
    }

    #[Test]
    public function create_account_requires_financial_receiving_update_permission(): void
    {
        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $this->validPayload())
            ->assertStatus(403);
    }

    #[Test]
    public function create_account_rejects_legacy_tenant_settings_permission_alone(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $this->validPayload())
            ->assertStatus(403);
    }

    #[Test]
    public function create_account_rejects_invalid_cpf(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        $payload = $this->validPayload();
        $payload['person']['tax_id'] = '111.111.111-11';

        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['person.tax_id']);
    }

    #[Test]
    public function create_account_requires_terms_accepted_at(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        $payload = $this->validPayload();
        unset($payload['terms_accepted_at']);

        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted_at']);
    }

    #[Test]
    public function create_account_succeeds_and_masks_the_document_in_the_response(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_HTTP_1',
                'token' => ['access_token' => 'ACCESS-HTTP', 'refresh_token' => 'REFRESH-HTTP', 'expires_in' => 100],
            ], 201),
        ]);

        $response = $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $this->validPayload())
            ->assertStatus(200);

        $response->assertJsonPath('data.status', TenantPagBankConnection::STATUS_SUBMITTED);
        $response->assertJsonPath('data.account_id', 'ACCO_HTTP_1');

        $maskedDocument = $response->json('data.document_masked');
        $this->assertNotSame('11144477735', $maskedDocument);
        $this->assertStringContainsString('*', (string) $maskedDocument);

        $raw = $response->getContent();
        $this->assertStringNotContainsString('ACCESS-HTTP', $raw);
        $this->assertStringNotContainsString('REFRESH-HTTP', $raw);
        $this->assertStringNotContainsString('11144477735', $raw);
    }

    #[Test]
    public function double_submit_does_not_create_two_accounts(): void
    {
        $this->grantPermission('financial_receiving', 'update');

        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_ONCE',
                'token' => ['access_token' => 'A', 'refresh_token' => 'R', 'expires_in' => 100],
            ], 201),
        ]);

        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $this->validPayload())
            ->assertStatus(200);

        $second = $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $this->validPayload())
            ->assertStatus(422);

        $second->assertJsonPath('code', 'PAGBANK_ACCOUNT_ALREADY_SUBMITTED');

        $this->assertSame(1, TenantPagBankConnection::query()->where('tenant_id', $this->tenant->id)->count());
    }

    #[Test]
    public function tenant_a_cannot_create_a_second_account_for_tenant_b_via_shared_connection_lookup(): void
    {
        $this->grantPermission('financial_receiving', 'update');

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
            'connection_type' => TenantPagBankConnection::CONNECTION_TYPE_CREATED_BY_PLATFORM,
            'status' => TenantPagBankConnection::STATUS_SUBMITTED,
            'account_id' => 'ACCO_OTHER_TENANT',
            'environment' => 'sandbox',
            'submitted_at' => now(),
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_A',
                'token' => ['access_token' => 'A', 'refresh_token' => 'R', 'expires_in' => 100],
            ], 201),
        ]);

        $this->auth()
            ->postJson('/api/v1/tenant-tools/pagbank-connect/create-account', $this->validPayload())
            ->assertStatus(200)
            ->assertJsonPath('data.account_id', 'ACCO_A');

        $this->assertDatabaseHas('tenant_pagbank_connections', [
            'tenant_id' => $this->tenant->id,
            'account_id' => 'ACCO_A',
        ]);
        $this->assertDatabaseHas('tenant_pagbank_connections', [
            'tenant_id' => $otherTenant->id,
            'account_id' => 'ACCO_OTHER_TENANT',
        ]);
    }
}
