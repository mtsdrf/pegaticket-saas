<?php

namespace Tests\Feature\Fiscal;

use App\Models\Client\Client;
use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Fiscal\TaxRule;
use App\Models\Location\Endereco;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class OrderFiscalPreviewEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantScopedUser('order-fiscal@example.com');
        $this->grantPermission('orders', 'read');
    }

    #[Test]
    public function shows_ready_preview_with_profile_and_tax_rule_match(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddresses();

        $this->tenant->update([
            'endereco_id' => $tenantAddress->id,
            'cnpj' => '12.345.678/0001-99',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_series' => '1',
            'fiscal_nfce_csc_id' => '000001',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $clientAddress->id,
            'name' => 'Cliente final',
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

        TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'tax_type' => 'icms',
            'scope' => [
                'cfop' => ['5102'],
                'ncm' => ['2203'],
                'uf_origin' => ['SP'],
                'uf_dest' => ['SP'],
            ],
            'rate_percent' => 18,
            'is_active' => true,
        ]);

        $order = $this->createOrderWithItem($client, $product);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/orders/' . $order->uuid . '/fiscal-preview')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.can_prepare', true)
            ->assertJsonPath('data.provider', 'manual')
            ->assertJsonPath('data.provider_mode', 'manual')
            ->assertJsonPath('data.official_submission_enabled', false)
            ->assertJsonPath('data.context.document_type', 'nfce')
            ->assertJsonPath('data.operation_profile.name', 'Venda varejo')
            ->assertJsonPath('data.line_items.0.resolved_cfop', '5102')
            ->assertJsonPath('data.line_items.0.matched_tax_rules.0.tax_type', 'icms')
            ->assertJsonPath('data.line_items.0.matched_tax_rules.0.rate_percent', 18);
    }

    #[Test]
    public function highlights_attention_when_fiscal_foundations_are_missing_for_the_order(): void
    {
        [$tenantAddress, $clientAddress] = $this->seedAddresses();

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
            ->getJson('/api/v1/orders/' . $order->uuid . '/fiscal-preview')
            ->assertOk()
            ->assertJsonPath('data.status', 'attention')
            ->assertJsonPath('data.can_prepare', false)
            ->assertJsonPath('data.operation_profile', null);

        $issues = collect($response->json('data.issues'));

        $this->assertTrue($issues->contains(fn (array $issue) => $issue['key'] === 'operation_profile'));
        $this->assertTrue($issues->contains(fn (array $issue) => str_starts_with($issue['key'], 'item_ncm_')));
    }

    #[Test]
    public function does_not_allow_previewing_order_from_another_tenant(): void
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
            ->getJson('/api/v1/orders/' . $order->uuid . '/fiscal-preview')
            ->assertStatus(404);
    }

    /**
     * @return array{0: Endereco, 1: Endereco}
     */
    private function seedAddresses(): array
    {
        return $this->seedAddressesForTenant($this->tenant);
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
            'name' => 'Refrigerantes',
            'is_active' => true,
        ]);

        return Product::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_type_id' => $type->id,
            'name' => 'Refrigerante',
            'price' => 12,
            'unit' => 'UN',
            'ncm' => '22030000',
            'origin' => '0',
            'default_cfop' => '5102',
            'csosn_cst' => '102',
            'is_available' => true,
        ], $overrides));
    }

    private function createOrderWithItem(Client $client, Product $product): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $this->createStockLocation($this->tenant)->id,
            'codigo' => 'PED-001',
            'total_amount' => 12,
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
            'unit_price' => 12,
            'line_total' => 12,
        ]);

        return $order;
    }

    private function createStockLocation(Tenant $tenant): StockLocation
    {
        return StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Estoque principal',
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
