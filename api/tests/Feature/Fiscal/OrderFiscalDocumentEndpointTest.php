<?php

namespace Tests\Feature\Fiscal;

use App\Contracts\Fiscal\FiscalProviderInterface;
use App\Models\Client\Client;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Location\Endereco;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use App\Services\Fiscal\FiscalProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class OrderFiscalDocumentEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantScopedUser('order-fiscal-document@example.com');
        $this->grantPermission('orders', 'read');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'cancel');
    }

    /**
     * @param array<string, mixed> $response
     */
    private function bindProviderStatusResponse(array $response): void
    {
        $provider = new class($response) implements FiscalProviderInterface
        {
            /**
             * @param array<string, mixed> $response
             */
            public function __construct(
                private readonly array $response,
            ) {
            }

            public function issue(FiscalDocument $document): array
            {
                return [
                    'fiscal_document_uuid' => $document->uuid,
                    'status' => 'provider_submitted',
                    'provider' => $document->provider,
                    'provider_document_id' => $document->provider_document_id,
                ];
            }

            public function cancel(FiscalDocument $document, string $reason): array
            {
                return [
                    'fiscal_document_uuid' => $document->uuid,
                    'status' => 'canceled',
                    'reason' => $reason,
                ];
            }

            public function getStatus(string $providerDocumentId): array
            {
                return [
                    'provider_document_id' => $providerDocumentId,
                    ...$this->response,
                ];
            }
        };

        $registry = new class($provider) extends FiscalProviderRegistry
        {
            public function __construct(
                private readonly FiscalProviderInterface $provider,
            ) {
            }

            public function forDocument(FiscalDocument $document): FiscalProviderInterface
            {
                return $this->provider;
            }
        };

        $this->app->instance(FiscalProviderRegistry::class, $registry);
    }

    #[Test]
    public function prepares_a_draft_fiscal_document_for_a_ready_order(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'csc-teste',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);
        Order::whereKey($order->id)->update(['stock_reserved' => false]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated()
            ->assertJsonPath('data.document_type', 'nfce')
            ->assertJsonPath('data.series', '1')
            ->assertJsonPath('data.document_number', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.provider', 'manual')
            ->assertJsonPath('data.payload_snapshot_summary.order_code', $order->codigo)
            ->assertJsonPath('data.payload_snapshot_summary.items_count', 1);

        $this->assertDatabaseHas('fiscal_documents', [
            'tenant_id' => $this->tenant->id,
            'documentable_type' => Order::class,
            'documentable_id' => $order->id,
            'document_type' => 'nfce',
            'status' => 'draft',
            'provider' => 'manual',
        ]);

        $document = FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->firstOrFail();

        $this->assertSame($order->codigo, data_get($document->payload_snapshot, 'operation.order_code'));
        $this->assertSame('Venda varejo', data_get($document->payload_snapshot, 'operation.operation_profile.name'));
        $this->assertSame('5102', data_get($document->payload_snapshot, 'items.0.cfop'));
        $this->assertSame('1', $document->series);
        $this->assertSame(1, $document->document_number);
        $this->assertSame(2, $this->tenant->fresh()->fiscal_next_nfce_number);
    }

    #[Test]
    public function reuses_existing_active_document_instead_of_creating_duplicates(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_next_nfce_number' => 7,
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'csc-teste',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $document = FiscalDocument::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'documentable_type' => Order::class,
            'documentable_id' => $order->id,
            'document_type' => 'nfce',
            'status' => 'draft',
            'provider' => 'manual',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated()
            ->assertJsonPath('data.uuid', $document->uuid);

        $fresh = $document->fresh();

        $this->assertSame(1, FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->count());
        $this->assertSame($order->codigo, data_get($fresh->payload_snapshot, 'operation.order_code'));
        $this->assertSame('1', $fresh->series);
        $this->assertSame(7, $fresh->document_number);
        $this->assertSame(8, $this->tenant->fresh()->fiscal_next_nfce_number);
    }

    #[Test]
    public function blocks_preparation_when_order_has_fiscal_errors(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente incompleto',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct([
            'ncm' => null,
            'origin' => null,
            'default_cfop' => null,
            'csosn_cst' => null,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertStatus(422)
            ->assertJsonPath('code', 'FISCAL_PREPARATION_BLOCKED');

        $issues = collect($response->json('errors.issues'));

        $this->assertTrue($issues->contains(fn (array $issue) => $issue['key'] === 'operation_profile'));
        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    #[Test]
    public function blocks_preparation_when_configured_provider_is_missing_required_token(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertStatus(422)
            ->assertJsonPath('code', 'FISCAL_PREPARATION_BLOCKED');

        $issues = collect($response->json('errors.issues'));
        $this->assertTrue($issues->contains(fn (array $issue) => $issue['key'] === 'provider_api_token'));
    }

    #[Test]
    public function shows_detailed_fiscal_document_for_order_when_it_exists(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_next_nfce_number' => 25,
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'csc-teste',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertOk()
            ->assertJsonPath('data.document_number', 25)
            ->assertJsonPath('data.series', '1')
            ->assertJsonPath('data.payload_snapshot.operation.order_code', $order->codigo)
            ->assertJsonPath('data.payload_snapshot.items.0.cfop', '5102');
    }

    #[Test]
    public function downloads_xml_preview_for_prepared_fiscal_document(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_next_nfce_number' => 25,
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'csc-teste',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->get('/api/v1/orders/' . $order->uuid . '/fiscal-document/xml-preview');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename="pedido-' . $order->codigo . '-rascunho-fiscal.xml"');
        $response->assertSee('<maskatsFiscalDraft', false);
        $response->assertSee($order->codigo, false);
        $response->assertSee('5102', false);
    }

    #[Test]
    public function submits_a_prepared_fiscal_document_to_the_provider(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api-focus',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'provider_submitted')
            ->assertJsonPath('data.provider', 'focus_nfe')
            ->assertJsonPath('data.provider_document_id', fn (mixed $value) => is_string($value) && str_starts_with($value, 'FOCUS_NFE-'));

        $document = FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('provider_submitted', $document->status);
        $this->assertNotNull($document->submitted_at);
        $this->assertSame('FOCUS_NFE-' . $document->uuid, $document->provider_document_id);
        $this->assertDatabaseHas('fiscal_provider_messages', [
            'tenant_id' => $this->tenant->id,
            'fiscal_document_id' => $document->id,
            'message_type' => 'submission',
            'provider' => 'focus_nfe',
            'provider_status' => 'provider_submitted',
        ]);
        $this->assertDatabaseHas('fiscal_document_attempts', [
            'tenant_id' => $this->tenant->id,
            'fiscal_document_id' => $document->id,
            'operation_type' => 'submit',
            'status' => 'succeeded',
            'provider' => 'focus_nfe',
        ]);
    }

    #[Test]
    public function syncs_status_of_a_submitted_fiscal_document(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api-focus',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'provider_submitted');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/sync-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider', 'focus_nfe')
            ->assertJsonPath('data.provider_document_id', 'FOCUS_NFE-' . FiscalDocument::query()
                ->where('documentable_type', Order::class)
                ->where('documentable_id', $order->id)
                ->latest('id')
                ->value('uuid'));

        $document = FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('pending', $document->status);
        $this->assertNotNull($document->provider_status_checked_at);
        $this->assertSame('focus_nfe', data_get($document->provider_response_payload, 'provider'));
        $this->assertSame('pending', data_get($document->provider_response_payload, 'status'));
        $this->assertDatabaseHas('fiscal_provider_messages', [
            'tenant_id' => $this->tenant->id,
            'fiscal_document_id' => $document->id,
            'message_type' => 'status_sync',
            'provider' => 'focus_nfe',
            'provider_status' => 'pending',
        ]);
        $this->assertDatabaseHas('fiscal_document_attempts', [
            'tenant_id' => $this->tenant->id,
            'fiscal_document_id' => $document->id,
            'operation_type' => 'sync_status',
            'status' => 'succeeded',
            'provider' => 'focus_nfe',
        ]);
    }

    #[Test]
    public function sync_materializes_authorized_provider_response_fields(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api-focus',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/submit')
            ->assertOk();

        $this->bindProviderStatusResponse([
            'provider' => 'focus_nfe',
            'status' => 'authorized',
            'access_key' => '35190730290845000160550010000000011000000018',
            'xml_content' => '<nfeProc versao="4.00"></nfeProc>',
            'pdf_path' => 'fiscal/nfce/autorizada-1.pdf',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/sync-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'authorized')
            ->assertJsonPath('data.access_key', '35190730290845000160550010000000011000000018')
            ->assertJsonPath('data.pdf_path', 'fiscal/nfce/autorizada-1.pdf')
            ->assertJsonPath('data.authorized_at', fn (mixed $value) => is_string($value) && $value !== '');

        $document = FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('authorized', $document->status);
        $this->assertNotNull($document->authorized_at);
        $this->assertSame('35190730290845000160550010000000011000000018', $document->access_key);
        $this->assertSame('<nfeProc versao="4.00"></nfeProc>', $document->xml_content);
        $this->assertSame('fiscal/nfce/autorizada-1.pdf', $document->pdf_path);
    }

    #[Test]
    public function sync_materializes_rejected_provider_response_reason(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api-focus',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/submit')
            ->assertOk();

        $this->bindProviderStatusResponse([
            'provider' => 'focus_nfe',
            'status' => 'rejected',
            'reason' => 'CFOP incompatível com a operação informada.',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/sync-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'CFOP incompatível com a operação informada.')
            ->assertJsonPath('data.rejected_at', fn (mixed $value) => is_string($value) && $value !== '');

        $document = FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('rejected', $document->status);
        $this->assertNotNull($document->rejected_at);
        $this->assertSame('CFOP incompatível com a operação informada.', $document->rejection_reason);
    }

    #[Test]
    public function detailed_document_response_includes_provider_message_history(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api-focus',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/submit')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/sync-status')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertOk()
            ->assertJsonCount(2, 'data.provider_messages')
            ->assertJsonCount(2, 'data.attempts')
            ->assertJsonPath('data.provider_messages.0.message_type', 'status_sync')
            ->assertJsonPath('data.provider_messages.1.message_type', 'submission')
            ->assertJsonPath('data.attempts.0.operation_type', 'sync_status')
            ->assertJsonPath('data.attempts.1.operation_type', 'submit');
    }

    #[Test]
    public function blocks_preparing_again_after_document_was_submitted_to_provider(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api-focus',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/submit')
            ->assertOk();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertStatus(422)
            ->assertJsonPath('code', 'FISCAL_PREPARATION_BLOCKED');

        $issues = collect($response->json('errors.issues'));
        $this->assertTrue($issues->contains(fn (array $issue) => $issue['key'] === 'submitted_fiscal_document'));
    }

    #[Test]
    public function uses_the_tenant_configured_provider_slug_when_preparing_the_document(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api-focus',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-csc',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated()
            ->assertJsonPath('data.provider', 'focus_nfe');
    }

    #[Test]
    public function returns_not_found_when_order_has_no_prepared_fiscal_document(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente sem documento',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();
        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertStatus(404)
            ->assertJsonPath('code', 'FISCAL_DOCUMENT_NOT_FOUND');
    }

    #[Test]
    public function cancels_prepared_fiscal_document_manually(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'csc-teste',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);
        Order::whereKey($order->id)->update(['stock_reserved' => false]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled');
    }

    #[Test]
    public function updating_order_items_invalidates_the_prepared_fiscal_document(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'csc-teste',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);
        $order->load('items.product');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $item = $order->items->firstOrFail();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/orders/' . $order->uuid . '/items', [
                'notes' => 'Pedido ajustado após preparação fiscal',
                'items' => [[
                    'uuid' => $item->uuid,
                    'product_uuid' => $item->product->uuid,
                    'quantity' => 1,
                ]],
            ])
            ->assertOk();

        $document = FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('canceled', $document->status);
        $this->assertSame(
            __('messages.order.fiscal_document_invalidated_after_order_update'),
            $document->rejection_reason
        );
    }

    #[Test]
    public function canceling_the_order_invalidates_the_prepared_fiscal_document(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddressesForTenant($this->tenant);

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'csc-teste',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente pronto',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $product = $this->createFiscalProduct();

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda varejo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'scope' => [
                'order_origin' => ['staff'],
                'fulfillment_type' => ['delivery'],
                'destination_type' => ['consumer_final'],
            ],
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);
        Order::whereKey($order->id)->update(['stock_reserved' => false]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/orders/' . $order->uuid . '/cancel', [
                'cancellation_reason' => 'Cliente desistiu',
            ])
            ->assertOk();

        $document = FiscalDocument::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('canceled', $document->status);
        $this->assertSame(
            __('messages.order.fiscal_document_invalidated_after_order_cancel'),
            $document->rejection_reason
        );
    }

    #[Test]
    public function does_not_allow_preparing_order_from_another_tenant(): void
    {
        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Outro tenant',
            'slug' => 'outro-' . Str::random(8),
            'is_active' => true,
        ]);

        $productCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Categoria',
            'is_active' => true,
        ]);
        $productType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $productCategory->id,
            'name' => 'Tipo',
            'is_active' => true,
        ]);
        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_type_id' => $productType->id,
            'name' => 'Produto',
            'price' => 10,
            'unit' => 'UN',
            'is_available' => true,
        ]);

        [$tenantAddress] = $this->seedAddressesForTenant($otherTenant);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'endereco_id' => $tenantAddress->id,
            'name' => 'Cliente externo',
            'is_active' => true,
        ]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $this->createStockLocation($otherTenant)->id,
            'codigo' => 'PED-EXT',
            'total_amount' => 10,
            'status' => 'confirmed',
            'origin' => 'staff',
            'fulfillment_type' => 'delivery',
        ]);

        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders/' . $order->uuid . '/fiscal-document')
            ->assertStatus(404);
    }

    /**
     * @return array{0: Endereco, 1: Endereco}
     */
    private function seedAddressesForTenant(Tenant $tenant): array
    {
        $estadoId = DB::table('estados')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sao Paulo',
            'uf' => 'SP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cidadeId = DB::table('cidades')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estadoId,
            'name' => 'Sao Paulo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bairroId = DB::table('bairros')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidadeId,
            'name' => 'Centro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantAddress = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'estado_id' => $estadoId,
            'cidade_id' => $cidadeId,
            'bairro_id' => $bairroId,
            'logradouro' => 'Rua da empresa',
            'is_active' => true,
        ]);

        $clientAddress = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'estado_id' => $estadoId,
            'cidade_id' => $cidadeId,
            'bairro_id' => $bairroId,
            'logradouro' => 'Rua do cliente',
            'is_active' => true,
        ]);

        return [$tenantAddress, $clientAddress];
    }

    private function createFiscalProduct(array $overrides = []): Product
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Bebidas',
            'is_active' => true,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $category->id,
            'name' => 'Refrigerante',
            'is_active' => true,
        ]);

        return Product::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_type_id' => $type->id,
            'name' => 'Cola 2L',
            'price' => 12.5,
            'unit' => 'UN',
            'is_available' => true,
            'ncm' => '2203',
            'origin' => '0',
            'default_cfop' => '5102',
            'csosn_cst' => '102',
        ], $overrides));
    }

    private function createOrderWithItem(Client $client, Product $product): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $this->createStockLocation($this->tenant)->id,
            'codigo' => 'PED-' . Str::upper(Str::random(6)),
            'total_amount' => 12.5,
            'status' => 'confirmed',
            'origin' => 'staff',
            'fulfillment_type' => 'delivery',
        ]);

        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 12.5,
            'line_total' => 12.5,
        ]);

        return $order;
    }

    private function createStockLocation(Tenant $tenant): StockLocation
    {
        return StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Principal',
            'is_active' => true,
        ]);
    }
}
