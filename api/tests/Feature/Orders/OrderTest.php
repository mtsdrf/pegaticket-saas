<?php

namespace Tests\Feature\Orders;

use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Models\Product\Product;
use App\Models\Product\ProductOption;
use App\Models\Product\ProductOptionGroup;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockBalance;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('maskats.parcela_vencimento_dia', 10);

        $this->setUpTenantScopedUser('order-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function creating_a_non_installment_order_computes_totals_and_reserves_stock(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                // total_amount não é um campo de item válido (só
                // product_uuid/quantity/unit_price) — sempre ignorado, o
                // total do pedido é sempre recalculado no backend.
                ['product_uuid' => $product->uuid, 'quantity' => 3, 'total_amount' => 999],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '76.50')
            ->assertJsonPath('data.items.0.unit_price', '25.50')
            ->assertJsonPath('data.items.0.line_total', '76.50')
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_delivered', false);

        $this->assertNotNull($response->json('data.due_date'));

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(100, $balance->quantity_on_hand);
        $this->assertEquals(3, $balance->quantity_reserved);
        $this->assertEquals(97, $balance->quantity_available);
    }

    #[Test]
    public function creating_an_order_persists_a_note_per_item(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1, 'notes' => 'Sem cebola'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.notes', 'Sem cebola');

        $this->assertDatabaseHas('order_items', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'notes' => 'Sem cebola',
        ]);
    }

    #[Test]
    public function creating_an_order_with_product_options_adds_option_totals_and_returns_selected_options(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30.00]);

        $group = ProductOptionGroup::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'name' => 'Adicionais',
            'min_select' => 1,
            'max_select' => 2,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cheese = ProductOption::create([
            'tenant_id' => $this->tenant->id,
            'product_option_group_id' => $group->id,
            'name' => 'Queijo extra',
            'price' => 4.50,
            'sort_order' => 1,
            'is_available' => true,
        ]);

        $bacon = ProductOption::create([
            'tenant_id' => $this->tenant->id,
            'product_option_group_id' => $group->id,
            'name' => 'Bacon',
            'price' => 6.00,
            'sort_order' => 2,
            'is_available' => true,
        ]);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [[
                'product_uuid' => $product->uuid,
                'quantity' => 2,
                'options' => [
                    ['product_option_uuid' => $cheese->uuid, 'quantity' => 1],
                    ['product_option_uuid' => $bacon->uuid, 'quantity' => 1],
                ],
            ]],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '81.00')
            ->assertJsonPath('data.items.0.line_total', '81.00')
            ->assertJsonPath('data.items.0.options.0.product_option.name', 'Queijo extra')
            ->assertJsonPath('data.items.0.options.1.product_option.name', 'Bacon');

        $this->assertDatabaseHas('order_item_options', [
            'product_option_id' => $cheese->id,
            'line_total' => 9.00,
        ]);
        $this->assertDatabaseHas('order_item_options', [
            'product_option_id' => $bacon->id,
            'line_total' => 12.00,
        ]);
    }

    /**
     * item.unit_price agora é opcional e, quando enviado, sobrescreve o
     * Product.price atual (desconto/acréscimo por linha, paridade com o
     * legado — decisão confirmada com o usuário em 2026-07-12). Substitui
     * o comportamento anterior de "unit_price sempre ignorado" — o teste
     * acima cobre o caso omitido (usa Product.price), este cobre o caso
     * enviado (sobrescreve).
     */
    #[Test]
    public function item_unit_price_override_is_honored_when_sent(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 3, 'unit_price' => 20],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '60.00')
            ->assertJsonPath('data.items.0.unit_price', '20.00')
            ->assertJsonPath('data.items.0.line_total', '60.00');

        // Estoque reservado usa a quantidade, não o preço — não muda.
        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(3, $balance->quantity_reserved);
    }

    /**
     * Roadmap 2.4 (atacado/varejo por categoria de cliente): sem unit_price
     * manual no item, OrderService::create() consulta ProductPricingService
     * — cliente com categoria com override cadastrado usa esse preço em vez
     * do Product.price puro.
     */
    #[Test]
    public function item_without_unit_price_uses_client_category_override_price(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);

        $category = $this->createClientCategory($this->tenant->id);
        $this->attachClientCategory($client, $category);
        $this->setProductCategoryPrice($product, $category, 18.00);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '18.00')
            ->assertJsonPath('data.items.0.line_total', '36.00')
            ->assertJsonPath('data.total_amount', '36.00');
    }

    /**
     * Cliente com duas categorias com override cadastrado pro mesmo
     * produto usa o MENOR preço entre elas (decisão confirmada com o
     * usuário — o cliente sempre recebe o melhor desconto pro qual se
     * qualifica).
     */
    #[Test]
    public function item_without_unit_price_uses_lowest_price_among_client_categories(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);

        $categoryA = $this->createClientCategory($this->tenant->id);
        $categoryB = $this->createClientCategory($this->tenant->id);
        $this->attachClientCategory($client, $categoryA);
        $this->attachClientCategory($client, $categoryB);
        $this->setProductCategoryPrice($product, $categoryA, 18.00);
        $this->setProductCategoryPrice($product, $categoryB, 15.00);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '15.00');
    }

    /**
     * unit_price manual continua com prioridade máxima mesmo quando o
     * cliente tem categoria com override cadastrado — regressão do
     * comportamento já existente, agora com uma camada nova no meio.
     */
    #[Test]
    public function explicit_unit_price_still_wins_over_client_category_override(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);

        $category = $this->createClientCategory($this->tenant->id);
        $this->attachClientCategory($client, $category);
        $this->setProductCategoryPrice($product, $category, 18.00);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1, 'unit_price' => 5.00],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '5.00');
    }

    /**
     * Cliente sem nenhuma categoria com override cadastrado pro produto
     * cai no Product.price puro, mesmo já tendo categoria(s) sem preço
     * configurado.
     */
    #[Test]
    public function item_without_unit_price_falls_back_to_product_price_when_client_has_no_applicable_override(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);

        $category = $this->createClientCategory($this->tenant->id);
        $this->attachClientCategory($client, $category);
        // Sem setProductCategoryPrice() — categoria existe, mas sem override pra este produto.

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '25.50');
    }

    /**
     * Fase 8 (migração de dados reais) encontrou produto vendido por peso
     * (ex.: queijo/doces por kg) com quantidade fracionária real nos
     * pedidos. R$50,00/kg * 0,5kg precisa bater exato em R$25,00, sem erro
     * de arredondamento (preço continua em centavos inteiros internamente,
     * só a quantidade é fracionária).
     */
    #[Test]
    public function order_with_fractional_quantity_computes_exact_totals_and_reserves_stock(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 50.00]);

        $this->stockEntry($this->tenant->id, $product, $location, 10);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 0.5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '25.00')
            ->assertJsonPath('data.items.0.quantity', '0.500')
            ->assertJsonPath('data.items.0.unit_price', '50.00')
            ->assertJsonPath('data.items.0.line_total', '25.00');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEqualsWithDelta(0.5, (float) $balance->quantity_reserved, 0.0001);
        $this->assertEqualsWithDelta(9.5, $balance->quantity_available, 0.0001);
    }

    #[Test]
    public function order_with_multiple_fractional_items_sums_line_totals_without_rounding_drift(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $productA = $this->createProduct($this->tenant->id, ['price' => 33.33]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 12.99]);

        $this->stockEntry($this->tenant->id, $productA, $location, 10);
        $this->stockEntry($this->tenant->id, $productB, $location, 10);

        // 33.33 * 0.333 = 11.09889 -> arredonda para 11.10
        // 12.99 * 0.7   = 9.093    -> arredonda para 9.09
        // Total precisa ser a SOMA das linhas já arredondadas (20.19), não
        // um recálculo independente sobre a soma das quantidades/preços.
        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productA->uuid, 'quantity' => 0.333],
                ['product_uuid' => $productB->uuid, 'quantity' => 0.7],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.line_total', '11.10')
            ->assertJsonPath('data.items.1.line_total', '9.09')
            ->assertJsonPath('data.total_amount', '20.19');

        $balanceA = StockBalance::where('product_id', $productA->id)->where('location_id', $location->id)->first();
        $balanceB = StockBalance::where('product_id', $productB->id)->where('location_id', $location->id)->first();
        $this->assertEqualsWithDelta(0.333, (float) $balanceA->quantity_reserved, 0.0001);
        $this->assertEqualsWithDelta(0.7, (float) $balanceB->quantity_reserved, 0.0001);
    }

    #[Test]
    public function creating_an_order_fails_and_persists_nothing_when_stock_is_insufficient(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 2);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(0, $balance->quantity_reserved);
    }

    #[Test]
    public function installment_order_splits_total_evenly_with_remainder_on_last_installment(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 33.33]);

        $this->stockEntry($this->tenant->id, $product, $location, 10);

        // total = 33.33 * 3 = 99.99 dividido em 3 parcelas -> 33.33 cada, bate exato.
        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 3,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ]);

        $response->assertStatus(201);
        $installments = $response->json('data.installments');
        $this->assertCount(3, $installments);

        $sum = array_reduce($installments, fn($carry, $i) => $carry + (float) $i['amount'], 0.0);
        $this->assertEqualsWithDelta((float) $response->json('data.total_amount'), $sum, 0.001);

        // Caso não divida igualmente: 100 / 3 = 33.33 + 33.33 + 33.34
        $product2 = $this->createProduct($this->tenant->id, ['price' => 100]);
        $this->stockEntry($this->tenant->id, $product2, $location, 10);

        $response2 = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 3,
            'items' => [
                ['product_uuid' => $product2->uuid, 'quantity' => 1],
            ],
        ]);

        $response2->assertStatus(201);
        $installments2 = $response2->json('data.installments');
        $amounts = array_map(fn($i) => $i['amount'], $installments2);

        $this->assertEquals(['33.33', '33.33', '33.34'], $amounts);
        $this->assertEquals('100.00', $response2->json('data.total_amount'));
    }

    #[Test]
    public function deliver_converts_reservation_into_real_exit(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $balanceBefore = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(20, $balanceBefore->quantity_on_hand);
        $this->assertEquals(5, $balanceBefore->quantity_reserved);
        $this->assertEquals(15, $balanceBefore->quantity_available);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', true);

        $balanceAfter = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(15, $balanceAfter->quantity_on_hand);
        $this->assertEquals(0, $balanceAfter->quantity_reserved);
        $this->assertEquals(15, $balanceAfter->quantity_available);
    }

    #[Test]
    public function paying_last_installment_cascades_to_order_paid_and_delivered(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->assertFalse($order['is_delivered']);
        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_delivered', false);

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[1]['uuid']}/pay");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_delivered', true);

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(18, $balance->quantity_on_hand);
        $this->assertEquals(0, $balance->quantity_reserved);
    }

    /**
     * Data de pagamento manual/futura — usuário pode informar paid_at
     * explícito, inclusive no passado ou no futuro (pagamento agendado,
     * caso de uso real, não validado como erro). Sem paid_at, comportamento
     * atual (now()) se mantém.
     */
    #[Test]
    public function pay_with_explicit_past_paid_at_is_persisted(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay", [
            'paid_at' => '2026-01-05 10:00:00',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_paid', true);
        $this->assertStringStartsWith('2026-01-05', $response->json('data.paid_at'));
    }

    #[Test]
    public function pay_with_explicit_future_paid_at_is_allowed(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $futureDate = now()->addMonths(2)->format('Y-m-d');

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay", [
            'paid_at' => $futureDate,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_paid', true);
        $this->assertStringStartsWith($futureDate, $response->json('data.paid_at'));
    }

    #[Test]
    public function pay_without_paid_at_uses_now(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay");

        $response->assertStatus(200)->assertJsonPath('data.is_paid', true);
        $this->assertStringStartsWith(now()->format('Y-m-d'), $response->json('data.paid_at'));
    }

    /**
     * Cascata de quitação total (última parcela paga): paid_at do PEDIDO
     * (não só da parcela) deve refletir o paid_at explícito informado
     * NESSA chamada de payInstallment, não now().
     */
    #[Test]
    public function paying_last_installment_with_explicit_paid_at_reflects_on_order_paid_at(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        $response = $this->auth()->patchJson(
            "/api/v1/orders/{$order['uuid']}/installments/{$installments[1]['uuid']}/pay",
            ['paid_at' => '2026-03-15 12:00:00']
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paid', true);

        $this->assertStringStartsWith('2026-03-15', $response->json('data.paid_at'));

        $paidInstallment = collect($response->json('data.installments'))
            ->firstWhere('uuid', $installments[1]['uuid']);
        $this->assertStringStartsWith('2026-03-15', $paidInstallment['paid_at']);
    }

    #[Test]
    public function cancel_is_blocked_when_an_installment_is_already_paid(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $this->grantPermission('orders', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function cancel_before_delivery_releases_reservation(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200)->assertJsonPath('data.cancellation_reason', 'Cliente desistiu');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(20, $balance->quantity_on_hand);
        $this->assertEquals(0, $balance->quantity_reserved);
        $this->assertEquals(20, $balance->quantity_available);
    }

    #[Test]
    public function cancel_after_delivery_returns_stock(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'deliver');
        $this->grantPermission('orders', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")->assertStatus(200);

        $balanceAfterDeliver = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(15, $balanceAfterDeliver->quantity_on_hand);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Produto com defeito',
        ])->assertStatus(200);

        $balanceAfterCancel = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(20, $balanceAfterCancel->quantity_on_hand);
        $this->assertEquals(0, $balanceAfterCancel->quantity_reserved);
    }

    #[Test]
    public function deliver_is_blocked_when_order_is_already_cancelled(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'deliver');
        $this->grantPermission('orders', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function calling_deliver_twice_returns_clean_422_not_server_error(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")->assertStatus(200);

        // Sem o lock de linha do Order, essa segunda chamada podia cair na
        // exceção de Estoque (reserva já convertida) em vez do 422 limpo de
        // estado inválido — regressão do achado de concorrência da revisão.
        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function calling_pay_twice_returns_clean_422_not_server_error(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay")->assertStatus(200);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    /**
     * O achado central de undeliver(): não basta devolver o estoque para
     * "disponível" (returnStock sozinho) — precisa recriar a RESERVA
     * (reserve() logo em seguida), senão um segundo deliver() no mesmo
     * pedido quebra em findReserveMovement() (nenhuma reserva ativa
     * apontando pro OrderItem). Este teste prova as duas pontas: o saldo
     * bate exatamente com o estado pré-entrega, e o segundo deliver()
     * funciona normalmente.
     */
    #[Test]
    public function undeliver_restores_reservation_and_a_second_deliver_works(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $balanceBeforeDeliver = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(20, $balanceBeforeDeliver->quantity_on_hand);
        $this->assertEquals(5, $balanceBeforeDeliver->quantity_reserved);
        $this->assertEquals(15, $balanceBeforeDeliver->quantity_available);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")->assertStatus(200);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/undeliver")
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', false);

        $this->assertNull(Order::where('uuid', $order['uuid'])->first()->delivered_at);

        $balanceAfterUndeliver = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals($balanceBeforeDeliver->quantity_on_hand, $balanceAfterUndeliver->quantity_on_hand);
        $this->assertEquals($balanceBeforeDeliver->quantity_reserved, $balanceAfterUndeliver->quantity_reserved);
        $this->assertEquals($balanceBeforeDeliver->quantity_available, $balanceAfterUndeliver->quantity_available);

        // Prova que a reserva foi recriada corretamente: um segundo
        // deliver() funciona sem cair em "reserva não encontrada".
        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', true);

        $balanceAfterSecondDeliver = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(15, $balanceAfterSecondDeliver->quantity_on_hand);
        $this->assertEquals(0, $balanceAfterSecondDeliver->quantity_reserved);
    }

    #[Test]
    public function undeliver_is_blocked_when_order_is_not_delivered(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/undeliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function undeliver_is_blocked_when_order_is_cancelled(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'deliver');
        $this->grantPermission('orders', 'pay');
        $this->grantPermission('orders', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        // Cancela antes de entregar (sem pagamento registrado, cancel() permite).
        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/undeliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function unpay_reverts_is_paid_and_paid_at(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay")->assertStatus(200);

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/unpay")
            ->assertStatus(200)
            ->assertJsonPath('data.is_paid', false);

        $fresh = Order::where('uuid', $order['uuid'])->first();
        $this->assertFalse((bool) $fresh->is_paid);
        $this->assertNull($fresh->paid_at);
    }

    #[Test]
    public function unpay_is_blocked_for_installment_order(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/unpay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function unpay_is_blocked_when_order_is_not_paid(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/unpay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    /**
     * Ponto mais fácil de errar do escopo: desfazer o pagamento da
     * última parcela reverte a cascata de order.is_paid (todas estavam
     * pagas -> volta pra "nem todas"), mas NÃO mexe em is_delivered —
     * entrega é um fato físico independente, já aconteceu, desfazer
     * pagamento não desfaz entrega (decisão de design documentada em
     * OrderService::unpayInstallment()).
     */
    #[Test]
    public function unpay_installment_reverts_order_cascade_without_touching_delivery(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        // Última parcela: cascata marca is_paid=true E is_delivered=true.
        $paidResponse = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[1]['uuid']}/pay");
        $paidResponse->assertStatus(200)
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_delivered', true);

        // Desfaz o pagamento da última parcela: is_paid do pedido volta
        // para false (nem todas as parcelas pagas mais), mas is_delivered
        // continua true — entrega já aconteceu de fato.
        $unpaidResponse = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[1]['uuid']}/unpay");
        $unpaidResponse->assertStatus(200)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_delivered', true);

        $fresh = Order::where('uuid', $order['uuid'])->first();
        $this->assertFalse((bool) $fresh->is_paid);
        $this->assertNull($fresh->paid_at);
        $this->assertTrue((bool) $fresh->is_delivered);
        $this->assertNotNull($fresh->delivered_at);

        $installment0Fresh = OrderInstallment::where('uuid', $installments[0]['uuid'])->first();
        $this->assertTrue((bool) $installment0Fresh->is_paid);
    }

    #[Test]
    public function unpay_installment_is_blocked_when_installment_is_not_paid(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}/unpay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function undeliver_and_unpay_endpoints_require_their_respective_permissions(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        // Sem orders,deliver e sem orders,pay concedidos.
        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/undeliver")->assertStatus(403);
        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/unpay")->assertStatus(403);
    }

    #[Test]
    public function show_and_mutating_actions_return_404_for_order_from_another_tenant(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'read');
        $this->grantPermission('orders', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        // Segundo tenant/usuário.
        $this->setUpTenantScopedUser('order-user-other@test.com');
        $this->grantPermission('orders', 'read');
        $this->grantPermission('orders', 'deliver');

        $this->auth()->getJson("/api/v1/orders/{$order['uuid']}")->assertStatus(404);
        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")->assertStatus(404);
    }

    #[Test]
    public function creating_order_without_stock_location_uses_tenant_default(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $default = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $default, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.stock_location.uuid', $default->uuid);
    }

    /**
     * mark_as_delivered ecoa o default "entregue" (true) do formulário
     * legado sem reabrir update genérico: dispara a mesma lógica de
     * deliver() (conversão de reserva -> saída real) dentro da própria
     * transação de criação. Não exige a permissão orders,deliver — é um
     * campo do POST /orders, guardado só por orders,create.
     */
    #[Test]
    public function mark_as_delivered_on_creation_converts_reservation_into_real_exit(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'mark_as_delivered' => true,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_delivered', true);

        $this->assertNotNull($response->json('data.delivered_at'));

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(15, $balance->quantity_on_hand);
        $this->assertEquals(0, $balance->quantity_reserved);
        $this->assertEquals(15, $balance->quantity_available);
    }

    /**
     * mark_as_paid ecoa o default "pago" (normalmente false, mas o form
     * legado permite marcar true) sem reabrir update genérico. Só faz
     * sentido pra pedido não parcelado — pagamento parcelado é sempre por
     * parcela.
     */
    #[Test]
    public function mark_as_paid_on_creation_marks_non_installment_order_as_paid(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_paid', true)
            // mark_as_paid não implica mark_as_delivered — são flags
            // independentes, cada uma só ecoa o próprio default do legado.
            ->assertJsonPath('data.is_delivered', false);

        $this->assertNotNull($response->json('data.paid_at'));
    }

    /**
     * Confirma que ambos os flags juntos aplicam as duas transições dentro
     * da mesma transação de criação, sem duplicar a integração de estoque
     * (performDelivery/performPayment compartilhados com deliver()/pay()).
     */
    #[Test]
    public function mark_as_delivered_and_mark_as_paid_together_apply_both_transitions(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'mark_as_delivered' => true,
            'mark_as_paid' => true,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_delivered', true)
            ->assertJsonPath('data.is_paid', true);

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(15, $balance->quantity_on_hand);
        $this->assertEquals(0, $balance->quantity_reserved);
    }

    #[Test]
    public function mark_as_paid_is_rejected_for_installment_order(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'mark_as_paid' => true,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('mark_as_paid', $response->json('errors'));

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function expected_delivery_date_is_persisted_and_returned(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'expected_delivery_date' => '2026-08-01',
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertStringStartsWith('2026-08-01', $response->json('data.expected_delivery_date'));

        $this->assertDatabaseHas('orders', [
            'uuid' => $response->json('data.uuid'),
            'expected_delivery_date' => '2026-08-01 00:00:00',
        ]);
    }

    #[Test]
    public function notes_over_500_characters_is_rejected(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'notes' => str_repeat('a', 501),
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('notes', $response->json('errors'));

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Rede de segurança do Model (Order::booted()) contra pedidos com
     * is_delivered/is_paid=true e a data correspondente nula — achado real
     * em produção no import legado (data de origem vazia/inválida). Todo
     * fluxo da aplicação (deliver()/pay()/create()) já seta a data
     * corretamente; este teste cobre uma escrita direta no Model (ex: um
     * import futuro) que esqueça de fazer isso.
     */
    #[Test]
    public function saving_delivered_or_paid_without_a_date_backfills_it_to_now(): void
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => true,
            'paid_at' => null,
            'is_delivered' => true,
            'delivered_at' => null,
        ]);

        $order->refresh();

        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->delivered_at);
    }

    /*
    |--------------------------------------------------------------------------
    | PUT /orders/{order}/items — edição de itens/cabeçalho
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function update_items_adds_a_new_item_and_recalculates_total(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 5]);

        $this->stockEntry($this->tenant->id, $productA, $location, 20);
        $this->stockEntry($this->tenant->id, $productB, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productA->uuid, 'quantity' => 3],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $productA->uuid, 'quantity' => 3],
                ['product_uuid' => $productB->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '40.00')
            ->assertJsonCount(2, 'data.items');

        $balanceB = StockBalance::where('product_id', $productB->id)->where('location_id', $location->id)->first();
        $this->assertEquals(2, $balanceB->quantity_reserved);
    }

    #[Test]
    public function update_items_removes_an_item_and_releases_its_reservation(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 5]);

        $this->stockEntry($this->tenant->id, $productA, $location, 20);
        $this->stockEntry($this->tenant->id, $productB, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productA->uuid, 'quantity' => 2],
                ['product_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ])->json('data');

        $itemA = collect($order['items'])->firstWhere('product.uuid', $productA->uuid);

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $itemA['uuid'], 'product_uuid' => $productA->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonCount(1, 'data.items');

        $balanceB = StockBalance::where('product_id', $productB->id)->where('location_id', $location->id)->first();
        $this->assertEquals(0, $balanceB->quantity_reserved);
        $this->assertEquals(20, $balanceB->quantity_available);
    }

    #[Test]
    public function update_items_changing_quantity_replaces_reservation_and_recalculates_total(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '50.00')
            ->assertJsonPath('data.items.0.quantity', '5.000');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(5, $balance->quantity_reserved);
        $this->assertEquals(15, $balance->quantity_available);
    }

    #[Test]
    public function update_items_changing_product_moves_reservation_between_products(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 8]);

        $this->stockEntry($this->tenant->id, $productA, $location, 20);
        $this->stockEntry($this->tenant->id, $productB, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productA->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $productB->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '16.00')
            ->assertJsonPath('data.items.0.product.uuid', $productB->uuid);

        $balanceA = StockBalance::where('product_id', $productA->id)->where('location_id', $location->id)->first();
        $balanceB = StockBalance::where('product_id', $productB->id)->where('location_id', $location->id)->first();
        $this->assertEquals(0, $balanceA->quantity_reserved);
        $this->assertEquals(2, $balanceB->quantity_reserved);
    }

    #[Test]
    public function update_items_edits_header_fields_without_touching_unchanged_items(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $otherLocation = $this->createLocation($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'notes' => 'Entregar após 18h',
            'stock_location_uuid' => $otherLocation->uuid,
            'expected_delivery_date' => '2026-09-01',
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'Entregar após 18h')
            ->assertJsonPath('data.stock_location.uuid', $otherLocation->uuid)
            ->assertJsonPath('data.total_amount', '20.00');

        $this->assertStringStartsWith('2026-09-01', $response->json('data.expected_delivery_date'));

        // Item não mudou (mesmo produto/quantidade) — reserva original
        // continua intacta, nenhum movimento de estoque ruído.
        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(2, $balance->quantity_reserved);
    }

    #[Test]
    public function update_items_is_blocked_when_order_is_already_delivered(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/deliver")->assertStatus(200);

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_is_blocked_when_order_is_already_paid(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay")->assertStatus(200);

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_is_blocked_when_order_is_cancelled(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_rejects_item_uuid_not_belonging_to_the_order(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'read');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 40);

        $orderA = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $orderB = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->json('data');

        $foreignItemUuid = $orderB['items'][0]['uuid'];

        $this->auth()->putJson("/api/v1/orders/{$orderA['uuid']}/items", [
            'items' => [
                ['uuid' => $foreignItemUuid, 'product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        // Nada foi alterado (tudo ou nada).
        $this->auth()->getJson("/api/v1/orders/{$orderA['uuid']}")
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function update_items_rejects_duplicate_uuid_in_the_same_payload(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $itemUuid = $order['items'][0]['uuid'];

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $itemUuid, 'product_uuid' => $product->uuid, 'quantity' => 2],
                ['uuid' => $itemUuid, 'product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_rejects_product_from_another_tenant(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $foreignTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
        $foreignProduct = $this->createProduct($foreignTenant->id, ['price' => 10]);

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $foreignProduct->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('items.0.product_uuid', $response->json('errors'));
    }

    #[Test]
    public function update_items_on_installment_order_changes_total_without_touching_installments(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->json('data');

        $originalInstallments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('data.total_amount', '50.00');

        $freshInstallments = $response->json('data.installments');
        $this->assertCount(count($originalInstallments), $freshInstallments);

        $sum = array_reduce($freshInstallments, fn($carry, $i) => $carry + (float) $i['amount'], 0.0);
        $this->assertEqualsWithDelta(30.0, $sum, 0.001);

        foreach ($originalInstallments as $index => $original) {
            $this->assertEquals($original['uuid'], $freshInstallments[$index]['uuid']);
            $this->assertEquals($original['amount'], $freshInstallments[$index]['amount']);
            $this->assertEquals($original['due_date'], $freshInstallments[$index]['due_date']);
        }
    }

    /**
     * orders.codigo (2026-07-15): número sequencial de exibição por
     * tenant, via tenants.next_order_code. Primeiro pedido do tenant
     * recebe "1000", segundo "1001", etc.
     */
    #[Test]
    public function creating_orders_assigns_sequential_codigo_starting_at_1000(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $payload = [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ];

        $this->auth()->postJson('/api/v1/orders', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.codigo', '1000');

        $this->auth()->postJson('/api/v1/orders', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.codigo', '1001');
    }

    #[Test]
    public function each_tenant_has_its_own_codigo_sequence_starting_at_1000(): void
    {
        $this->grantPermission('orders', 'create');
        $clientA = $this->createClient($this->tenant->id);
        $locationA = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $productA, $locationA, 20);
        $tokenA = $this->token;

        $this->setUpTenantScopedUser('order-codigo-tenant-b@test.com');
        $this->grantPermission('orders', 'create');
        $clientB = $this->createClient($this->tenant->id);
        $locationB = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $productB, $locationB, 20);
        $tokenB = $this->token;

        $orderA = $this->withHeader('Authorization', 'Bearer ' . $tokenA)->postJson('/api/v1/orders', [
            'client_uuid' => $clientA->uuid,
            'stock_location_uuid' => $locationA->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productA->uuid, 'quantity' => 1],
            ],
        ]);

        $orderB = $this->withHeader('Authorization', 'Bearer ' . $tokenB)->postJson('/api/v1/orders', [
            'client_uuid' => $clientB->uuid,
            'stock_location_uuid' => $locationB->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ]);

        $orderA->assertStatus(201)->assertJsonPath('data.codigo', '1000');
        $orderB->assertStatus(201)->assertJsonPath('data.codigo', '1000');
    }

    #[Test]
    public function direct_order_model_creation_also_assigns_sequential_codigo(): void
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);

        DB::table('tenants')->where('id', $this->tenant->id)->update(['next_order_code' => 999]);

        $firstOrder = Order::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_delivered' => false,
            'notes' => 'Pedido direto 1',
            'status' => 'confirmed',
            'origin' => 'staff',
            'stock_reserved' => false,
        ]);

        $secondOrder = Order::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 20,
            'is_paid' => false,
            'is_delivered' => false,
            'notes' => 'Pedido direto 2',
            'status' => 'confirmed',
            'origin' => 'staff',
            'stock_reserved' => false,
        ]);

        $this->assertSame('1000', $firstOrder->fresh()->codigo);
        $this->assertSame('1001', $secondOrder->fresh()->codigo);
        $this->assertEquals(1001, DB::table('tenants')->where('id', $this->tenant->id)->value('next_order_code'));
    }

    /**
     * paid_amount (2026-07-15): pagamento total continua gravando
     * paid_amount = total_amount, comportamento preexistente preservado.
     */
    #[Test]
    public function paying_full_amount_sets_paid_amount_equal_to_total(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.total_amount', '50.00');

        // assertJsonPath compara com ===; json_encode(50.0) sem cast
        // grava "50" (sem casa decimal), então float 50.0 vs int
        // decodificado quebraria um assertJsonPath direto — assertEquals
        // é loose e cobre os dois casos.
        $this->assertEquals(50.0, $response->json('data.paid_amount'));
    }

    /**
     * Pagamento PARCIAL (paridade com legado valor_pago, pedido id=12608
     * confirmado no banco legado): is_paid continua false, paid_amount
     * grava o valor informado, sem cascata de entrega.
     */
    #[Test]
    public function paying_a_partial_amount_registers_paid_amount_without_marking_order_as_paid(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 10],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay", [
            'amount' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('message', __('messages.order.partially_paid'));

        $this->assertEquals(50.0, $response->json('data.paid_amount'));
        $this->assertNull($response->json('data.delivered_at'));

        $fresh = Order::where('uuid', $order['uuid'])->first();
        $this->assertFalse((bool) $fresh->is_paid);
        $this->assertFalse((bool) $fresh->is_delivered);
        $this->assertEquals('50.00', $fresh->paid_amount);
    }

    #[Test]
    public function paying_an_amount_greater_than_or_equal_to_total_behaves_as_full_payment(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay", [
            'amount' => 50,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_paid', true);
        $this->assertEquals(50.0, $response->json('data.paid_amount'));
    }

    #[Test]
    public function unpay_after_full_payment_resets_paid_amount_to_null(): void
    {
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'pay');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/pay")->assertStatus(200);

        $response = $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/unpay");

        $response->assertStatus(200)->assertJsonPath('data.paid_amount', null);

        $fresh = Order::where('uuid', $order['uuid'])->first();
        $this->assertNull($fresh->paid_amount);
    }

    /**
     * Comando orders:backfill-codigo (2026-07-15) — pedidos "legados"
     * simulados apagando codigo/next_order_code manualmente após criação.
     * Confirma sequência correta por tenant e que tenants.next_order_code
     * fica consistente pro próximo pedido criado depois do backfill.
     */
    #[Test]
    public function backfill_codigo_command_assigns_sequential_codes_per_tenant_and_updates_next_order_code(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $tenantA = $this->tenant;

        for ($i = 0; $i < 2; $i++) {
            $this->auth()->postJson('/api/v1/orders', [
                'client_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'is_installment' => false,
                'items' => [
                    ['product_uuid' => $product->uuid, 'quantity' => 1],
                ],
            ])->assertStatus(201);
        }

        // Simula pedidos legados: apaga codigo e reseta o contador do tenant.
        Order::where('tenant_id', $tenantA->id)->update(['codigo' => null]);
        DB::table('tenants')->where('id', $tenantA->id)->update(['next_order_code' => 1000]);

        $this->setUpTenantScopedUser('order-codigo-backfill-b@test.com');
        $this->grantPermission('orders', 'create');
        $clientB = $this->createClient($this->tenant->id);
        $locationB = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $productB, $locationB, 100);

        $tenantB = $this->tenant;

        $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $clientB->uuid,
            'stock_location_uuid' => $locationB->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        Order::where('tenant_id', $tenantB->id)->update(['codigo' => null]);
        DB::table('tenants')->where('id', $tenantB->id)->update(['next_order_code' => 1000]);

        $this->artisan('orders:backfill-codigo')->assertExitCode(0);

        $this->assertEquals(
            ['1000', '1001'],
            Order::where('tenant_id', $tenantA->id)->orderBy('id')->pluck('codigo')->all()
        );

        $this->assertEquals(
            ['1000'],
            Order::where('tenant_id', $tenantB->id)->orderBy('id')->pluck('codigo')->all()
        );

        // next_order_code guarda o ÚLTIMO código emitido (não o próximo),
        // mesma convenção de OrderService::create() — increment-then-read.
        $this->assertEquals(1001, DB::table('tenants')->where('id', $tenantA->id)->value('next_order_code'));
        $this->assertEquals(1000, DB::table('tenants')->where('id', $tenantB->id)->value('next_order_code'));

        // Pedido criado depois do backfill não colide com os códigos
        // recém-atribuídos.
        $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $clientB->uuid,
            'stock_location_uuid' => $locationB->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->assertJsonPath('data.codigo', '1001');
    }

    /**
     * Guards novos do checkout público (Delivery Fase 2 — horário de
     * funcionamento, pedido mínimo, taxa de entrega por bairro) vivem
     * inteiramente em StorefrontCheckoutService::checkout(), nunca em
     * OrderService::create(). POST /orders (fluxo staff) não passa por
     * esse Service, então nenhum tenant_settings novo (mínimo) nem
     * StoreBusinessHour/StoreDeliveryFee configurados (ou ausentes)
     * bloqueiam o pedido — delivery_fee sempre nasce 0 por default do
     * DTO/coluna.
     */
    #[Test]
    public function staff_order_creation_is_unaffected_by_delivery_fee_phase_2_guards(): void
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);

        $this->stockEntry($this->tenant->id, $product, $location, 10);

        // Nenhum StoreBusinessHour, StoreDeliveryFee ou minimum_order_value
        // configurado pra este tenant — se algum guard novo vazasse pro
        // fluxo staff, este pedido seria bloqueado.
        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '50.00');

        $this->assertEquals(0.0, (float) $response->json('data.delivery_fee'));

        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertEquals(0.0, (float) $order->delivery_fee);
    }
}
