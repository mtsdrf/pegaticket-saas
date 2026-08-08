<?php

namespace Tests\Feature\Payment;

use App\DTOs\Payment\CreatePagBankSellerAccountDTO;
use App\Jobs\Payment\SyncPagBankTenantAccountJob;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use App\Services\Payment\PagBankAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sincronização periódica do caminho Account/Cadastro (roadmap R2.5,
 * seção 9.5.5). Deliberadamente não é um ShouldQueue job (ver docblock da
 * classe) — testado como uma unidade de trabalho síncrona, mesmo padrão
 * de PagBankAccountServiceTest (Http::fake, sem chamada de rede real).
 */
class SyncPagBankTenantAccountJobTest extends TestCase
{
    use RefreshDatabase;

    private SyncPagBankTenantAccountJob $job;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-application-token');
        Config::set('services.pagbank.connect_client_id', 'fake-client-id');
        Config::set('services.pagbank.connect_client_secret', 'fake-client-secret');

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Sync Job',
            'slug' => 'tenant-sync-job-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->job = app(SyncPagBankTenantAccountJob::class);
    }

    private function createSubmittedConnection(): TenantPagBankConnection
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_JOB_1',
                'token' => ['access_token' => 'ACCESS-JOB', 'refresh_token' => 'REFRESH-JOB', 'expires_in' => 100],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray([
            'person_type' => 'pf',
            'email' => 'seller-job@test.com',
            'terms_accepted_at' => now()->toIso8601String(),
            'person' => [
                'name' => 'Job Test',
                'birth_date' => '1994-07-03',
                'mother_name' => 'Mother Job',
                'tax_id' => '111.444.777-35',
                'address' => [
                    'street' => 'Rua Job', 'number' => '1', 'complement' => null,
                    'locality' => 'Centro', 'city' => 'São Paulo', 'region_code' => 'SP',
                    'postal_code' => '01000-000', 'country' => 'BRA',
                ],
                'phone' => ['country' => '55', 'area' => '11', 'number' => '981111111'],
            ],
            'company' => null,
        ]);

        return app(PagBankAccountService::class)->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');
    }

    #[Test]
    public function syncs_a_submitted_connection_that_became_verified_at_pagbank(): void
    {
        $connection = $this->createSubmittedConnection();
        $this->assertSame(TenantPagBankConnection::STATUS_SUBMITTED, $connection->status);

        Http::fake([
            'sandbox.api.pagseguro.com/accounts/ACCO_JOB_1' => Http::response([
                'id' => 'ACCO_JOB_1',
                'status' => 'ACTIVE',
            ], 200),
        ]);

        $result = $this->job->handle($connection);

        $this->assertTrue($result);
        $this->assertSame(TenantPagBankConnection::STATUS_VERIFIED, $connection->refresh()->status);
    }

    #[Test]
    public function a_network_failure_is_logged_and_does_not_throw(): void
    {
        $connection = $this->createSubmittedConnection();

        Http::fake([
            'sandbox.api.pagseguro.com/accounts/ACCO_JOB_1' => Http::response(['message' => 'boom'], 500),
        ]);

        $result = $this->job->handle($connection);

        $this->assertFalse($result);
        $this->assertSame(TenantPagBankConnection::STATUS_SUBMITTED, $connection->refresh()->status);
    }

    #[Test]
    public function connections_in_a_status_that_does_not_need_sync_are_skipped(): void
    {
        Http::fake();

        $connection = TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'connection_type' => TenantPagBankConnection::CONNECTION_TYPE_CONNECTED_EXISTING,
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'environment' => 'sandbox',
        ]);

        $result = $this->job->handle($connection);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    #[Test]
    public function not_configured_connections_are_skipped(): void
    {
        Http::fake();

        $connection = TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'connection_type' => TenantPagBankConnection::CONNECTION_TYPE_CREATED_BY_PLATFORM,
            'status' => TenantPagBankConnection::STATUS_NOT_CONFIGURED,
            'environment' => 'sandbox',
        ]);

        $result = $this->job->handle($connection);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }
}
