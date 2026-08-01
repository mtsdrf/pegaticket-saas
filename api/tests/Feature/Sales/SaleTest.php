<?php

namespace Tests\Feature\Sales;

use App\Models\Sale\Sale;
use App\Models\Sale\SaleInstallment;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);

        $this->setUpTenantScopedUser('order-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function creating_a_non_installment_order_computes_totals_and_reserves_stock(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                // total_amount não é um campo de item válido (só
                // ticket_type_uuid/quantity/unit_price) — sempre ignorado, o
                // total do pedido é sempre recalculado no backend.
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3, 'total_amount' => 999],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '76.50')
            ->assertJsonPath('data.items.0.unit_price', '25.50')
            ->assertJsonPath('data.items.0.line_total', '76.50')
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_delivered', false);

        $this->assertNotNull($response->json('data.due_date'));

    }

    #[Test]
    public function creating_an_order_persists_a_note_per_item(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1, 'notes' => 'Sem cebola'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.notes', 'Sem cebola');

        $this->assertDatabaseHas('order_items', [
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $product->id,
            'notes' => 'Sem cebola',
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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3, 'unit_price' => 20],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '60.00')
            ->assertJsonPath('data.items.0.unit_price', '20.00')
            ->assertJsonPath('data.items.0.line_total', '60.00');

        // Estoque reservado usa a quantidade, não o preço — não muda.
    }

    /**
     * unit_price manual continua com prioridade máxima sobre o preço de
     * tabela calculado pelo servidor.
     */
    #[Test]
    public function explicit_unit_price_still_wins_over_default_price(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25.50]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1, 'unit_price' => 5.00],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '5.00');
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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 50.00]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 0.5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '25.00')
            ->assertJsonPath('data.items.0.quantity', '0.500')
            ->assertJsonPath('data.items.0.unit_price', '50.00')
            ->assertJsonPath('data.items.0.line_total', '25.00');

    }

    #[Test]
    public function order_with_multiple_fractional_items_sums_line_totals_without_rounding_drift(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $productA = $this->createProduct($this->tenant->id, ['price' => 33.33]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 12.99]);


        // 33.33 * 0.333 = 11.09889 -> arredonda para 11.10
        // 12.99 * 0.7   = 9.093    -> arredonda para 9.09
        // Total precisa ser a SOMA das linhas já arredondadas (20.19), não
        // um recálculo independente sobre a soma das quantidades/preços.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productA->uuid, 'quantity' => 0.333],
                ['ticket_type_uuid' => $productB->uuid, 'quantity' => 0.7],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.line_total', '11.10')
            ->assertJsonPath('data.items.1.line_total', '9.09')
            ->assertJsonPath('data.total_amount', '20.19');

    }

    #[Test]
    public function installment_order_splits_total_evenly_with_remainder_on_last_installment(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 33.33]);


        // total = 33.33 * 3 = 99.99 dividido em 3 parcelas -> 33.33 cada, bate exato.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 3,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ]);

        $response->assertStatus(201);
        $installments = $response->json('data.installments');
        $this->assertCount(3, $installments);

        $sum = array_reduce($installments, fn($carry, $i) => $carry + (float) $i['amount'], 0.0);
        $this->assertEqualsWithDelta((float) $response->json('data.total_amount'), $sum, 0.001);

        // Caso não divida igualmente: 100 / 3 = 33.33 + 33.33 + 33.34
        $product2 = $this->createProduct($this->tenant->id, ['price' => 100]);

        $response2 = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 3,
            'items' => [
                ['ticket_type_uuid' => $product2->uuid, 'quantity' => 1],
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
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');


        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', true);

    }

    #[Test]
    public function paying_last_installment_cascades_to_order_paid_and_delivered(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->assertFalse($order['is_delivered']);
        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_delivered', false);

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[1]['uuid']}/pay");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_delivered', true);

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
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay", [
            'paid_at' => '2026-01-05 10:00:00',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_paid', true);
        $this->assertStringStartsWith('2026-01-05', $response->json('data.paid_at'));
    }

    #[Test]
    public function pay_with_explicit_future_paid_at_is_allowed(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $futureDate = now()->addMonths(2)->format('Y-m-d');

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay", [
            'paid_at' => $futureDate,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_paid', true);
        $this->assertStringStartsWith($futureDate, $response->json('data.paid_at'));
    }

    #[Test]
    public function pay_without_paid_at_uses_now(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay");

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
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        $response = $this->auth()->patchJson(
            "/api/v1/sales/{$order['uuid']}/installments/{$installments[1]['uuid']}/pay",
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
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $this->grantPermission('sales', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function cancel_before_delivery_releases_reservation(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200)->assertJsonPath('data.cancellation_reason', 'Cliente desistiu');

    }

    #[Test]
    public function cancel_after_delivery_returns_stock(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'deliver');
        $this->grantPermission('sales', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")->assertStatus(200);


        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Produto com defeito',
        ])->assertStatus(200);

    }

    #[Test]
    public function deliver_is_blocked_when_order_is_already_cancelled(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'deliver');
        $this->grantPermission('sales', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function calling_deliver_twice_returns_clean_422_not_server_error(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")->assertStatus(200);

        // Sem o lock de linha do Sale, essa segunda chamada podia cair na
        // exceção de Estoque (reserva já convertida) em vez do 422 limpo de
        // estado inválido — regressão do achado de concorrência da revisão.
        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function calling_pay_twice_returns_clean_422_not_server_error(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay")->assertStatus(200);

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    /**
     * O achado central de undeliver(): não basta devolver o estoque para
     * "disponível" (returnStock sozinho) — precisa recriar a RESERVA
     * (reserve() logo em seguida), senão um segundo deliver() no mesmo
     * pedido quebra em findReserveMovement() (nenhuma reserva ativa
     * apontando pro SaleItem). Este teste prova as duas pontas: o saldo
     * bate exatamente com o estado pré-entrega, e o segundo deliver()
     * funciona normalmente.
     */
    #[Test]
    public function undeliver_restores_reservation_and_a_second_deliver_works(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');


        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")->assertStatus(200);

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/undeliver")
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', false);

        $this->assertNull(Sale::where('uuid', $order['uuid'])->first()->delivered_at);


        // Prova que a reserva foi recriada corretamente: um segundo
        // deliver() funciona sem cair em "reserva não encontrada".
        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', true);

    }

    #[Test]
    public function undeliver_is_blocked_when_order_is_not_delivered(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/undeliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function undeliver_is_blocked_when_order_is_cancelled(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'deliver');
        $this->grantPermission('sales', 'pay');
        $this->grantPermission('sales', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        // Cancela antes de entregar (sem pagamento registrado, cancel() permite).
        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/undeliver")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function unpay_reverts_is_paid_and_paid_at(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay")->assertStatus(200);

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/unpay")
            ->assertStatus(200)
            ->assertJsonPath('data.is_paid', false);

        $fresh = Sale::where('uuid', $order['uuid'])->first();
        $this->assertFalse((bool) $fresh->is_paid);
        $this->assertNull($fresh->paid_at);
    }

    #[Test]
    public function unpay_is_blocked_for_installment_order(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/unpay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function unpay_is_blocked_when_order_is_not_paid(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/unpay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    /**
     * Ponto mais fácil de errar do escopo: desfazer o pagamento da
     * última parcela reverte a cascata de order.is_paid (todas estavam
     * pagas -> volta pra "nem todas"), mas NÃO mexe em is_delivered —
     * entrega é um fato físico independente, já aconteceu, desfazer
     * pagamento não desfaz entrega (decisão de design documentada em
     * SaleService::unpayInstallment()).
     */
    #[Test]
    public function unpay_installment_reverts_order_cascade_without_touching_delivery(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        // Última parcela: cascata marca is_paid=true E is_delivered=true.
        $paidResponse = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[1]['uuid']}/pay");
        $paidResponse->assertStatus(200)
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_delivered', true);

        // Desfaz o pagamento da última parcela: is_paid do pedido volta
        // para false (nem todas as parcelas pagas mais), mas is_delivered
        // continua true — entrega já aconteceu de fato.
        $unpaidResponse = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[1]['uuid']}/unpay");
        $unpaidResponse->assertStatus(200)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_delivered', true);

        $fresh = Sale::where('uuid', $order['uuid'])->first();
        $this->assertFalse((bool) $fresh->is_paid);
        $this->assertNull($fresh->paid_at);
        $this->assertTrue((bool) $fresh->is_delivered);
        $this->assertNotNull($fresh->delivered_at);

        $installment0Fresh = SaleInstallment::where('uuid', $installments[0]['uuid'])->first();
        $this->assertTrue((bool) $installment0Fresh->is_paid);
    }

    #[Test]
    public function unpay_installment_is_blocked_when_installment_is_not_paid(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/installments/{$installments[0]['uuid']}/unpay")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function undeliver_and_unpay_endpoints_require_their_respective_permissions(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        // Sem orders,deliver e sem orders,pay concedidos.
        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/undeliver")->assertStatus(403);
        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/unpay")->assertStatus(403);
    }

    #[Test]
    public function show_and_mutating_actions_return_404_for_order_from_another_tenant(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'read');
        $this->grantPermission('sales', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        // Segundo tenant/usuário.
        $this->setUpTenantScopedUser('order-user-other@test.com');
        $this->grantPermission('sales', 'read');
        $this->grantPermission('sales', 'deliver');

        $this->auth()->getJson("/api/v1/sales/{$order['uuid']}")->assertStatus(404);
        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")->assertStatus(404);
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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_delivered' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_delivered', true);

        $this->assertNotNull($response->json('data.delivered_at'));

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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_delivered' => true,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_delivered', true)
            ->assertJsonPath('data.is_paid', true);

    }

    #[Test]
    public function mark_as_paid_is_rejected_for_installment_order(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('mark_as_paid', $response->json('errors'));

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function expected_delivery_date_is_persisted_and_returned(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'expected_delivery_date' => '2026-08-01',
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'notes' => str_repeat('a', 501),
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('notes', $response->json('errors'));

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Rede de segurança do Model (Sale::booted()) contra pedidos com
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

        $order = Sale::create([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
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
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 5]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productA->uuid, 'quantity' => 3],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $productA->uuid, 'quantity' => 3],
                ['ticket_type_uuid' => $productB->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '40.00')
            ->assertJsonCount(2, 'data.items');

    }

    #[Test]
    public function update_items_removes_an_item_and_releases_its_reservation(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 5]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productA->uuid, 'quantity' => 2],
                ['ticket_type_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ])->json('data');

        $itemA = collect($order['items'])->firstWhere('ticket_type.uuid', $productA->uuid);

        $response = $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $itemA['uuid'], 'ticket_type_uuid' => $productA->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonCount(1, 'data.items');

    }

    #[Test]
    public function update_items_changing_quantity_replaces_reservation_and_recalculates_total(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '50.00')
            ->assertJsonPath('data.items.0.quantity', '5.000');

    }

    #[Test]
    public function update_items_changing_product_moves_reservation_between_products(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $productB = $this->createProduct($this->tenant->id, ['price' => 8]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productA->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $productB->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '16.00')
            ->assertJsonPath('data.items.0.ticket_type.uuid', $productB->uuid);

    }

    #[Test]
    public function update_items_edits_header_fields_without_touching_unchanged_items(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $response = $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'notes' => 'Entregar após 18h',
            'expected_delivery_date' => '2026-09-01',
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'Entregar após 18h')
            ->assertJsonPath('data.total_amount', '20.00');

        $this->assertStringStartsWith('2026-09-01', $response->json('data.expected_delivery_date'));

        // Item não mudou (mesmo produto/quantidade) — reserva original
        // continua intacta, nenhum movimento de estoque ruído.
    }

    #[Test]
    public function update_items_is_blocked_when_order_is_already_delivered(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'deliver');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/deliver")->assertStatus(200);

        $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_is_blocked_when_order_is_already_paid(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay")->assertStatus(200);

        $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_is_blocked_when_order_is_cancelled(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'cancel');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_rejects_item_uuid_not_belonging_to_the_order(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'read');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $orderA = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $orderB = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->json('data');

        $foreignItemUuid = $orderB['items'][0]['uuid'];

        $this->auth()->putJson("/api/v1/sales/{$orderA['uuid']}/items", [
            'items' => [
                ['uuid' => $foreignItemUuid, 'ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        // Nada foi alterado (tudo ou nada).
        $this->auth()->getJson("/api/v1/sales/{$orderA['uuid']}")
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function update_items_rejects_duplicate_uuid_in_the_same_payload(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->json('data');

        $itemUuid = $order['items'][0]['uuid'];

        $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $itemUuid, 'ticket_type_uuid' => $product->uuid, 'quantity' => 2],
                ['uuid' => $itemUuid, 'ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function update_items_rejects_product_from_another_tenant(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
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

        $response = $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $foreignProduct->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('items.0.ticket_type_uuid', $response->json('errors'));
    }

    #[Test]
    public function update_items_on_installment_order_changes_total_without_touching_installments(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => true,
            'installments_count' => 2,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->json('data');

        $originalInstallments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/sales/{$order['uuid']}/items", [
            'items' => [
                ['uuid' => $order['items'][0]['uuid'], 'ticket_type_uuid' => $product->uuid, 'quantity' => 5],
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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $payload = [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ];

        $this->auth()->postJson('/api/v1/sales', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.codigo', '1000');

        $this->auth()->postJson('/api/v1/sales', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.codigo', '1001');
    }

    #[Test]
    public function each_tenant_has_its_own_codigo_sequence_starting_at_1000(): void
    {
        $this->grantPermission('sales', 'create');
        $clientA = $this->createClient($this->tenant->id);
        $productA = $this->createProduct($this->tenant->id, ['price' => 10]);
        $tokenA = $this->token;

        $this->setUpTenantScopedUser('order-codigo-tenant-b@test.com');
        $this->grantPermission('sales', 'create');
        $clientB = $this->createClient($this->tenant->id);
        $productB = $this->createProduct($this->tenant->id, ['price' => 10]);
        $tokenB = $this->token;

        $orderA = $this->withHeader('Authorization', 'Bearer ' . $tokenA)->postJson('/api/v1/sales', [
            'final_customer_uuid' => $clientA->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productA->uuid, 'quantity' => 1],
            ],
        ]);

        $orderB = $this->withHeader('Authorization', 'Bearer ' . $tokenB)->postJson('/api/v1/sales', [
            'final_customer_uuid' => $clientB->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ]);

        $orderA->assertStatus(201)->assertJsonPath('data.codigo', '1000');
        $orderB->assertStatus(201)->assertJsonPath('data.codigo', '1000');
    }

    #[Test]
    public function direct_order_model_creation_also_assigns_sequential_codigo(): void
    {
        $client = $this->createClient($this->tenant->id);

        DB::table('tenants')->where('id', $this->tenant->id)->update(['next_order_code' => 999]);

        $firstOrder = Sale::create([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_delivered' => false,
            'notes' => 'Pedido direto 1',
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        $secondOrder = Sale::create([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 20,
            'is_paid' => false,
            'is_delivered' => false,
            'notes' => 'Pedido direto 2',
            'status' => 'confirmed',
            'origin' => 'staff',
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
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay");

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
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 10],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay", [
            'amount' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('message', __('messages.order.partially_paid'));

        $this->assertEquals(50.0, $response->json('data.paid_amount'));
        $this->assertNull($response->json('data.delivered_at'));

        $fresh = Sale::where('uuid', $order['uuid'])->first();
        $this->assertFalse((bool) $fresh->is_paid);
        $this->assertFalse((bool) $fresh->is_delivered);
        $this->assertEquals('50.00', $fresh->paid_amount);
    }

    #[Test]
    public function paying_an_amount_greater_than_or_equal_to_total_behaves_as_full_payment(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay", [
            'amount' => 50,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_paid', true);
        $this->assertEquals(50.0, $response->json('data.paid_amount'));
    }

    #[Test]
    public function unpay_after_full_payment_resets_paid_amount_to_null(): void
    {
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'pay');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);


        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 5],
            ],
        ])->json('data');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/pay")->assertStatus(200);

        $response = $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/unpay");

        $response->assertStatus(200)->assertJsonPath('data.paid_amount', null);

        $fresh = Sale::where('uuid', $order['uuid'])->first();
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
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $tenantA = $this->tenant;

        for ($i = 0; $i < 2; $i++) {
            $this->auth()->postJson('/api/v1/sales', [
                'final_customer_uuid' => $client->uuid,
                'is_installment' => false,
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
                ],
            ])->assertStatus(201);
        }

        // Simula pedidos legados: apaga codigo e reseta o contador do tenant.
        Sale::where('tenant_id', $tenantA->id)->update(['codigo' => null]);
        DB::table('tenants')->where('id', $tenantA->id)->update(['next_order_code' => 1000]);

        $this->setUpTenantScopedUser('order-codigo-backfill-b@test.com');
        $this->grantPermission('sales', 'create');
        $clientB = $this->createClient($this->tenant->id);
        $productB = $this->createProduct($this->tenant->id, ['price' => 10]);

        $tenantB = $this->tenant;

        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $clientB->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        Sale::where('tenant_id', $tenantB->id)->update(['codigo' => null]);
        DB::table('tenants')->where('id', $tenantB->id)->update(['next_order_code' => 1000]);

        $this->artisan('orders:backfill-codigo')->assertExitCode(0);

        $this->assertEquals(
            ['1000', '1001'],
            Sale::where('tenant_id', $tenantA->id)->orderBy('id')->pluck('codigo')->all()
        );

        $this->assertEquals(
            ['1000'],
            Sale::where('tenant_id', $tenantB->id)->orderBy('id')->pluck('codigo')->all()
        );

        // next_order_code guarda o ÚLTIMO código emitido (não o próximo),
        // mesma convenção de SaleService::create() — increment-then-read.
        $this->assertEquals(1001, DB::table('tenants')->where('id', $tenantA->id)->value('next_order_code'));
        $this->assertEquals(1000, DB::table('tenants')->where('id', $tenantB->id)->value('next_order_code'));

        // Pedido criado depois do backfill não colide com os códigos
        // recém-atribuídos.
        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $clientB->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $productB->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->assertJsonPath('data.codigo', '1001');
    }

    /**
     * Guards do checkout público (horário de funcionamento e pedido mínimo)
     * vivem inteiramente em StorefrontCheckoutService::checkout(), nunca em
     * SaleService::create(). POST /orders (fluxo staff) não passa por esse
     * Service, então StoreBusinessHour e minimum_order_value não bloqueiam
     * o pedido manual.
     */
    #[Test]
    public function staff_order_creation_is_unaffected_by_delivery_fee_phase_2_guards(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);


        // Nenhum StoreBusinessHour ou minimum_order_value configurado pra
        // este tenant — se algum guard do checkout vazasse pro fluxo staff,
        // este pedido seria bloqueado.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '50.00');

        $this->assertEquals(0.0, (float) $response->json('data.delivery_fee'));

        $order = Sale::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertEquals(0.0, (float) $order->delivery_fee);
    }
}
