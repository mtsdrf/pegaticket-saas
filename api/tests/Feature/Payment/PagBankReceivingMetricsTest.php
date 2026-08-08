<?php

namespace Tests\Feature\Payment;

use App\DTOs\Payment\CreatePagBankSellerAccountDTO;
use App\Events\Payment\TenantPagBankConnectionStatusChanged;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use App\Services\Payment\PagBankAccountService;
use App\Services\Payment\PagBankConnectService;
use App\Services\Payment\PagBankTransactionLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Observabilidade do subdomínio de recebimentos (roadmap R2.5, seção
 * 9.9) — o projeto não tem Prometheus/StatsD, então cada métrica listada
 * na especificação vira um ponto de instrumentação via
 * PagBankTransactionLogger::metric() (log estruturado no canal
 * `pagbank_transactions`). Estes testes substituem essa dependência por
 * um spy (Mockery::spy + bind no container) e confirmam que o ponto de
 * instrumentação é chamado nos fluxos principais — não reimplementam
 * Http::fake dos fluxos (já cobertos em PagBankConnectServiceTest/
 * PagBankAccountServiceTest).
 */
class PagBankReceivingMetricsTest extends TestCase
{
    use RefreshDatabase;

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
            'name' => 'Tenant Metrics',
            'slug' => 'tenant-metrics-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function bindMetricsSpy(): PagBankTransactionLogger
    {
        $spy = Mockery::spy(PagBankTransactionLogger::class);
        $this->app->instance(PagBankTransactionLogger::class, $spy);

        return $spy;
    }

    #[Test]
    public function starting_the_connect_flow_logs_setup_started_and_connect_started(): void
    {
        $spy = $this->bindMetricsSpy();

        app(PagBankConnectService::class)->buildAuthorizationUrl($this->tenant->id);

        $spy->shouldHaveReceived('metric')->with('pagbank_receiving_setup_started_total', Mockery::type('array'))->once();
        $spy->shouldHaveReceived('metric')->with('pagbank_connect_started_total', Mockery::type('array'))->once();
    }

    #[Test]
    public function a_successful_connect_callback_logs_connect_success(): void
    {
        $spy = $this->bindMetricsSpy();
        $service = app(PagBankConnectService::class);
        $service->buildAuthorizationUrl($this->tenant->id);

        $state = TenantPagBankConnection::where('tenant_id', $this->tenant->id)->value('connect_state');

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/token' => Http::response([
                'access_token' => 'ACCESS',
                'refresh_token' => 'REFRESH',
                'account_id' => 'ACC_1',
                'expires_in' => 100,
            ], 200),
        ]);

        $service->handleCallback('some-code', $state);

        $spy->shouldHaveReceived('metric')->with('pagbank_connect_success_total', Mockery::type('array'))->once();
    }

    #[Test]
    public function an_invalid_state_on_callback_logs_connect_failed(): void
    {
        $spy = $this->bindMetricsSpy();

        try {
            app(PagBankConnectService::class)->handleCallback('code', 'invalid-state');
        } catch (\Throwable) {
            // esperado
        }

        $spy->shouldHaveReceived('metric')->with('pagbank_connect_failed_total', Mockery::type('array'))->once();
    }

    #[Test]
    public function creating_a_seller_account_logs_setup_started_and_accounts_created(): void
    {
        $spy = $this->bindMetricsSpy();

        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_METRIC_1',
                'token' => ['access_token' => 'A', 'refresh_token' => 'R', 'expires_in' => 100],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray([
            'person_type' => 'pf',
            'email' => 'seller-metric@test.com',
            'terms_accepted_at' => now()->toIso8601String(),
            'person' => [
                'name' => 'Metric Test',
                'birth_date' => '1994-07-03',
                'mother_name' => 'Mother Metric',
                'tax_id' => '111.444.777-35',
                'address' => [
                    'street' => 'Rua Metric', 'number' => '1', 'complement' => null,
                    'locality' => 'Centro', 'city' => 'São Paulo', 'region_code' => 'SP',
                    'postal_code' => '01000-000', 'country' => 'BRA',
                ],
                'phone' => ['country' => '55', 'area' => '11', 'number' => '981111111'],
            ],
            'company' => null,
        ]);

        app(PagBankAccountService::class)->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');

        $spy->shouldHaveReceived('metric')->with('pagbank_receiving_setup_started_total', Mockery::type('array'))->once();
        $spy->shouldHaveReceived('metric')->with('pagbank_accounts_created_total', Mockery::type('array'))->once();
    }

    #[Test]
    public function status_changing_to_enabled_or_verified_logs_tenant_receiving_enabled_total(): void
    {
        $spy = $this->bindMetricsSpy();

        event(new TenantPagBankConnectionStatusChanged(
            tenantId: $this->tenant->id,
            actorId: 0,
            fromStatus: 'under_review',
            toStatus: 'enabled'
        ));

        $spy->shouldHaveReceived('metric')->with('tenant_receiving_enabled_total', Mockery::type('array'))->once();
    }

    #[Test]
    public function status_changing_to_restricted_or_rejected_logs_tenant_receiving_restricted_total(): void
    {
        $spy = $this->bindMetricsSpy();

        event(new TenantPagBankConnectionStatusChanged(
            tenantId: $this->tenant->id,
            actorId: 0,
            fromStatus: 'enabled',
            toStatus: 'restricted'
        ));

        $spy->shouldHaveReceived('metric')->with('tenant_receiving_restricted_total', Mockery::type('array'))->once();
    }
}
