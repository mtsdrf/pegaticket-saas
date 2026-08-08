<?php

namespace Tests\Feature\Console;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `pagbank:sync-receiving-accounts` (roadmap R2.5, seção 9.5.5).
 */
class SyncPagBankReceivingAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-application-token');
        Config::set('services.pagbank.connect_client_id', 'fake-client-id');
        Config::set('services.pagbank.connect_client_secret', 'fake-client-secret');
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant '.$slug,
            'slug' => $slug.'-'.Str::random(6),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function createConnection(Tenant $tenant, string $status, string $accountId): TenantPagBankConnection
    {
        return TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'provider' => 'pagbank',
            'connection_type' => TenantPagBankConnection::CONNECTION_TYPE_CREATED_BY_PLATFORM,
            'status' => $status,
            'account_id' => $accountId,
            'access_token_encrypted' => 'ACCESS-'.$accountId,
            'environment' => 'sandbox',
        ]);
    }

    #[Test]
    public function syncs_every_eligible_connection_up_to_the_default_limit(): void
    {
        $tenantA = $this->createTenant('sync-a');
        $tenantB = $this->createTenant('sync-b');
        $this->createConnection($tenantA, TenantPagBankConnection::STATUS_SUBMITTED, 'ACC_A');
        $this->createConnection($tenantB, TenantPagBankConnection::STATUS_UNDER_REVIEW, 'ACC_B');
        // Estado que não deve ser sincronizado.
        $enabledTenant = $this->createTenant('sync-enabled');
        $this->createConnection($enabledTenant, TenantPagBankConnection::STATUS_ENABLED, 'ACC_C');

        Http::fake([
            'sandbox.api.pagseguro.com/accounts/ACC_A' => Http::response(['id' => 'ACC_A', 'status' => 'ACTIVE'], 200),
            'sandbox.api.pagseguro.com/accounts/ACC_B' => Http::response(['id' => 'ACC_B', 'status' => 'PENDING'], 200),
        ]);

        $exitCode = Artisan::call('pagbank:sync-receiving-accounts');

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            TenantPagBankConnection::STATUS_VERIFIED,
            TenantPagBankConnection::query()->where('tenant_id', $tenantA->id)->value('status')
        );
        Http::assertNotSent(fn ($request) => $request->url() === 'https://sandbox.api.pagseguro.com/accounts/ACC_C');
    }

    #[Test]
    public function limit_option_caps_how_many_connections_are_processed(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $tenant = $this->createTenant('sync-limit-'.$i);
            $this->createConnection($tenant, TenantPagBankConnection::STATUS_SUBMITTED, 'ACC_LIMIT_'.$i);
        }

        Http::fake([
            'sandbox.api.pagseguro.com/accounts/*' => Http::response(['status' => 'PENDING'], 200),
        ]);

        Artisan::call('pagbank:sync-receiving-accounts', ['--limit' => 1]);

        Http::assertSentCount(1);
    }

    #[Test]
    public function tenant_id_option_restricts_sync_to_a_single_tenant(): void
    {
        $tenantA = $this->createTenant('sync-tenant-a');
        $tenantB = $this->createTenant('sync-tenant-b');
        $this->createConnection($tenantA, TenantPagBankConnection::STATUS_SUBMITTED, 'ACC_TA');
        $this->createConnection($tenantB, TenantPagBankConnection::STATUS_SUBMITTED, 'ACC_TB');

        Http::fake([
            'sandbox.api.pagseguro.com/accounts/ACC_TA' => Http::response(['status' => 'ACTIVE'], 200),
        ]);

        Artisan::call('pagbank:sync-receiving-accounts', ['--tenant_id' => $tenantA->uuid]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.api.pagseguro.com/accounts/ACC_TA');
    }

    #[Test]
    public function a_single_failing_connection_does_not_block_the_others(): void
    {
        $tenantA = $this->createTenant('sync-fail-a');
        $tenantB = $this->createTenant('sync-fail-b');
        $this->createConnection($tenantA, TenantPagBankConnection::STATUS_SUBMITTED, 'ACC_FAIL_A');
        $this->createConnection($tenantB, TenantPagBankConnection::STATUS_SUBMITTED, 'ACC_FAIL_B');

        Http::fake([
            'sandbox.api.pagseguro.com/accounts/ACC_FAIL_A' => Http::response(['message' => 'boom'], 500),
            'sandbox.api.pagseguro.com/accounts/ACC_FAIL_B' => Http::response(['status' => 'ACTIVE'], 200),
        ]);

        $exitCode = Artisan::call('pagbank:sync-receiving-accounts');

        $this->assertNotSame(0, $exitCode);
        $this->assertSame(
            TenantPagBankConnection::STATUS_VERIFIED,
            TenantPagBankConnection::query()->where('tenant_id', $tenantB->id)->value('status')
        );
    }
}
