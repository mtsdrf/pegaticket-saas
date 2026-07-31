<?php

namespace Tests\Feature\Orders;

use App\Models\Order\OrderInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Gestão manual de parcela (POST/PUT/DELETE /orders/{order}/installments)
 * — correção do bug do legado (CRUD livre de parcela sem validar soma).
 * Ver App\Services\Order\OrderInstallmentService e
 * .claude/memory/architecture-decisions.md.
 */
class OrderInstallmentTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);

        $this->setUpTenantScopedUser('order-installment-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * @return array{0: array, 1: string} [order (json), installment_uuid da parcela 1]
     */
    protected function createInstallmentOrder(int $installmentsCount = 2, float $price = 50): array
    {
        $this->grantPermission('orders', 'create');

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => $price]);

        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => true,
            'installments_count' => $installmentsCount,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201)->json('data');

        return $order;
    }

    // --- Guardas ---

    #[Test]
    public function cannot_create_installment_for_non_installment_order(): void
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
        ])->assertStatus(201)->json('data');

        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 1,
            'amount' => 10,
            'due_date' => '2026-08-10',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function cannot_create_or_edit_or_delete_installment_for_cancelled_order(): void
    {
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'delete');
        $this->grantPermission('orders', 'cancel');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 3,
            'amount' => 10,
            'due_date' => '2026-08-10',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}", [
            'installment_number' => 1,
            'amount' => 50,
            'due_date' => '2026-08-10',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->auth()->deleteJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}")
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function paid_installment_cannot_be_edited_or_deleted(): void
    {
        $this->grantPermission('orders', 'pay');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'delete');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}", [
            'installment_number' => 1,
            'amount' => 50,
            'due_date' => '2026-08-10',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->auth()->deleteJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}")
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function duplicate_installment_number_is_rejected_on_create_and_update(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        // create com número já usado (parcela 1 já existe)
        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 1,
            'amount' => 10,
            'due_date' => '2026-08-10',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        // update tentando usar o número da OUTRA parcela existente
        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}", [
            'installment_number' => 2,
            'amount' => 50,
            'due_date' => '2026-08-10',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function create_breaking_the_total_sum_is_rejected(): void
    {
        $this->grantPermission('orders', 'update');

        // total=100 (2x50), já soma exato — qualquer nova parcela quebra a soma.
        $order = $this->createInstallmentOrder();

        $response = $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 3,
            'amount' => 10,
            'due_date' => '2026-08-10',
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        // Nada foi persistido — rollback da transação.
        $this->assertDatabaseCount('order_installments', 2);
    }

    #[Test]
    public function update_breaking_the_total_sum_is_rejected(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}", [
            'installment_number' => 1,
            'amount' => 30, // era 50 — sem editar a outra, soma vira 80 != 100
            'due_date' => '2026-08-10',
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->assertDatabaseHas('order_installments', [
            'uuid' => $installments[0]['uuid'],
            'amount' => 50.00,
        ]);
    }

    #[Test]
    public function delete_breaking_the_total_sum_is_rejected(): void
    {
        $this->grantPermission('orders', 'delete');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        $response = $this->auth()->deleteJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}");

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->assertDatabaseCount('order_installments', 2);
        $this->assertDatabaseHas('order_installments', ['uuid' => $installments[0]['uuid']]);
    }

    #[Test]
    public function order_from_another_tenant_returns_404(): void
    {
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'delete');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        $this->setUpTenantScopedUser('order-installment-user-other@test.com');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'delete');

        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 3,
            'amount' => 10,
            'due_date' => '2026-08-10',
        ])->assertStatus(404);

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}", [
            'installment_number' => 1,
            'amount' => 50,
            'due_date' => '2026-08-10',
        ])->assertStatus(404);

        $this->auth()->deleteJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}")
            ->assertStatus(404);
    }

    // --- Caminho feliz ---

    /**
     * A validação de soma (regra 4) é estrita: depois de QUALQUER
     * create/update/delete a soma tem que bater exatamente com o total —
     * o que significa que redistribuir valor entre parcelas via 2 chamadas
     * de API independentes nunca é possível sem um estado intermediário
     * inválido (é álgebra: mudar só 1 parcela nunca preserva a soma de um
     * total fixo, a menos que o valor final já seja o correto). Por isso
     * o "ajuste de outra parcela" do cenário de teste é montado via
     * manipulação direta do Eloquent (arrange), não via uma segunda
     * chamada de API — replica um cenário real (ex.: correção point-in-time
     * de um estado já existente) e testa exatamente o que a Service
     * valida: o resultado FINAL da operação bate com o total. Ver
     * architecture-decisions.md para a limitação de UX encontrada (não é
     * possível ao usuário final "abrir uma nova parcela puxando de outra"
     * em 2 cliques sem passar por um 422 intermediário).
     */
    #[Test]
    public function creating_a_new_installment_succeeds_when_sum_still_matches_total(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(); // 2 parcelas de 50 (total 100)
        $installments = $order['installments'];

        // Abre espaço pra parcela nova reduzindo a #2 direto no banco
        // (fora da API — simula um estado pré-existente a corrigir).
        OrderInstallment::where('uuid', $installments[1]['uuid'])->update(['amount' => 30.00]);

        $response = $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 3,
            'amount' => 20,
            'due_date' => '2026-09-10',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.installment_number', 3)
            ->assertJsonPath('data.amount', '20.00')
            ->assertJsonPath('data.is_paid', false);

        $this->assertDatabaseCount('order_installments', 3);
    }

    #[Test]
    public function editing_installment_amount_succeeds_when_sum_still_matches_total(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(); // 2 parcelas de 50 (total 100)
        $installments = $order['installments'];

        // Reduz a #2 direto no banco pra abrir espaço pra #1 crescer.
        OrderInstallment::where('uuid', $installments[1]['uuid'])->update(['amount' => 30.00]);

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}", [
            'installment_number' => 1,
            'amount' => 70,
            'due_date' => '2026-08-15',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.amount', '70.00');
        $this->assertStringStartsWith('2026-08-15', $response->json('data.due_date'));

        $this->assertDatabaseHas('order_installments', [
            'uuid' => $installments[0]['uuid'],
            'amount' => 70.00,
        ]);
    }

    /**
     * Editar só installment_number/due_date (sem tocar amount) é sempre
     * uma operação trivialmente válida — não muda a soma.
     */
    #[Test]
    public function editing_installment_number_and_due_date_without_changing_amount_always_succeeds(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(3, 33.33); // 3 parcelas, remainder na última
        $installments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}", [
            'installment_number' => $installments[0]['installment_number'],
            'amount' => $installments[0]['amount'],
            'due_date' => '2027-01-01',
        ]);

        $response->assertStatus(200);
        $this->assertStringStartsWith('2027-01-01', $response->json('data.due_date'));
    }

    #[Test]
    public function deleting_and_recreating_an_installment_succeeds_when_sum_still_matches_total(): void
    {
        $this->grantPermission('orders', 'delete');
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(); // 2 parcelas de 50 (total 100)
        $installments = $order['installments'];

        // Delete só é válido se, sem a parcela removida, a soma já bate —
        // engenheira isso ajustando a #1 pra já cobrir o total inteiro
        // (100.00) antes de excluir a #2.
        OrderInstallment::where('uuid', $installments[0]['uuid'])->update(['amount' => 100.00]);

        $this->auth()->deleteJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[1]['uuid']}")
            ->assertStatus(204);

        // delete() é soft delete (BaseModel) — a linha continua na tabela
        // com deleted_at preenchido, não é removida fisicamente.
        $this->assertSoftDeleted('order_installments', ['uuid' => $installments[1]['uuid']]);
        $this->assertEquals(1, OrderInstallment::whereNull('deleted_at')->count());

        // Recria uma parcela nova cobrindo a diferença.
        OrderInstallment::where('uuid', $installments[0]['uuid'])->update(['amount' => 60.00]);

        $response = $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 5,
            'amount' => 40,
            'due_date' => '2026-10-10',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(2, OrderInstallment::whereNull('deleted_at')->count());
    }

    // --- PUT /orders/{order}/installments (lote) ---

    /**
     * O caso que motivou o endpoint de lote: redistribuir valor entre
     * parcelas — impossível via os 3 endpoints individuais sem passar
     * por um 422 intermediário (ver architecture-decisions.md). Aqui a
     * soma só é validada UMA VEZ no final, então funciona numa chamada
     * só, exatamente como um usuário real faria pela UI.
     */
    #[Test]
    public function reallocate_redistributes_three_installments_into_two(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(3, 50); // total 100, parcelas 33.33+33.33+33.34
        $installments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installments' => [
                ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 60, 'due_date' => '2026-08-10'],
                ['uuid' => $installments[1]['uuid'], 'installment_number' => 2, 'amount' => 40, 'due_date' => '2026-09-10'],
                // installments[2] (parcela #3) não referenciada -> excluída.
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $amounts = array_map(fn($i) => $i['amount'], $data);
        sort($amounts);
        $this->assertEquals(['40.00', '60.00'], $amounts);

        $this->assertEquals(2, OrderInstallment::whereNull('deleted_at')->count());
        $this->assertSoftDeleted('order_installments', ['uuid' => $installments[2]['uuid']]);
    }

    #[Test]
    public function reallocate_can_add_a_new_installment_while_keeping_the_sum(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(); // 2 parcelas de 50 (total 100)
        $installments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installments' => [
                ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 20, 'due_date' => '2026-08-10'],
                ['uuid' => $installments[1]['uuid'], 'installment_number' => 2, 'amount' => 30, 'due_date' => '2026-09-10'],
                ['installment_number' => 3, 'amount' => 50, 'due_date' => '2026-10-10'],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(3, OrderInstallment::whereNull('deleted_at')->count());
    }

    #[Test]
    public function reallocate_rejects_payload_referencing_a_paid_installment(): void
    {
        $this->grantPermission('orders', 'pay');
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[0]['uuid']}/pay")
            ->assertStatus(200);

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installments' => [
                ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 50, 'due_date' => '2026-08-10'],
                ['uuid' => $installments[1]['uuid'], 'installment_number' => 2, 'amount' => 50, 'due_date' => '2026-09-10'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        // Nada mudou — parcela paga continua paga, a outra continua com o valor original.
        $this->assertDatabaseHas('order_installments', [
            'uuid' => $installments[1]['uuid'],
            'amount' => 50.00,
        ]);
    }

    #[Test]
    public function reallocate_with_wrong_final_sum_changes_nothing(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(); // 2 parcelas de 50 (total 100)
        $installments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installments' => [
                ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 40, 'due_date' => '2026-08-10'],
                ['uuid' => $installments[1]['uuid'], 'installment_number' => 2, 'amount' => 40, 'due_date' => '2026-09-10'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        // Tudo ou nada — os valores originais continuam intactos.
        $this->assertDatabaseHas('order_installments', ['uuid' => $installments[0]['uuid'], 'amount' => 50.00]);
        $this->assertDatabaseHas('order_installments', ['uuid' => $installments[1]['uuid'], 'amount' => 50.00]);
        $this->assertEquals(2, OrderInstallment::whereNull('deleted_at')->count());
    }

    #[Test]
    public function reallocate_is_blocked_for_cancelled_order(): void
    {
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'cancel');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertStatus(200);

        $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installments' => [
                ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 100, 'due_date' => '2026-08-10'],
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    /**
     * Bug real reportado pelo frontend (2026-07-12): excluir uma parcela
     * e criar uma nova reaproveitando o MESMO installment_number no
     * mesmo payload de reallocate() dava 500 cru. Causa raiz: a
     * constraint única (order_id, installment_number) não filtra
     * deleted_at — soft delete sozinho não libera o número no banco, e
     * o processamento original criava a parcela nova ANTES de excluir a
     * antiga. Corrigido com uma fase intermediária que move todo número
     * existente pra um sentinela único antes de aplicar qualquer
     * exclusão/criação/edição real (ver OrderInstallmentService::reallocate).
     */
    #[Test]
    public function reallocate_can_delete_an_installment_and_create_a_new_one_reusing_the_same_number(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(); // 2 parcelas de 50 (total 100), números 1 e 2
        $installments = $order['installments'];

        $response = $this->auth()->putJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installments' => [
                // Mantém a #1 intacta, exclui a #2 (não referenciada) e
                // cria uma parcela NOVA reaproveitando installment_number=2.
                ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 50, 'due_date' => '2026-08-10'],
                ['installment_number' => 2, 'amount' => 50, 'due_date' => '2026-09-10'],
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $numbers = array_map(fn($i) => $i['installment_number'], $data);
        sort($numbers);
        $this->assertEquals([1, 2], $numbers);

        $this->assertSoftDeleted('order_installments', ['uuid' => $installments[1]['uuid']]);
        $this->assertEquals(2, OrderInstallment::whereNull('deleted_at')->count());
    }

    /**
     * Mesma causa raiz, via os 2 endpoints individuais em chamadas
     * separadas (não só dentro de um único reallocate()): DELETE de uma
     * parcela seguido de POST de uma parcela nova com o mesmo número,
     * em requisições HTTP distintas.
     */
    #[Test]
    public function individual_delete_then_create_can_reuse_the_same_installment_number(): void
    {
        $this->grantPermission('orders', 'delete');
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder(); // 2 parcelas de 50 (total 100), números 1 e 2
        $installments = $order['installments'];

        // Abre espaço pra excluir a #2 sem quebrar a soma.
        OrderInstallment::where('uuid', $installments[0]['uuid'])->update(['amount' => 100.00]);

        $this->auth()->deleteJson("/api/v1/orders/{$order['uuid']}/installments/{$installments[1]['uuid']}")
            ->assertStatus(204);

        // Reabre espaço pra criar a nova #2.
        OrderInstallment::where('uuid', $installments[0]['uuid'])->update(['amount' => 50.00]);

        $response = $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/installments", [
            'installment_number' => 2,
            'amount' => 50,
            'due_date' => '2026-09-10',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.installment_number', 2);
        $this->assertEquals(2, OrderInstallment::whereNull('deleted_at')->count());
    }

    /**
     * Bug real reportado pelo frontend (2026-07-12): erro de validação
     * veio em inglês mesmo com Accept-Language: pt_BR. Causa raiz: as
     * chaves de messages.php (business rules, ex. installment_sum_mismatch)
     * já estavam corretas nos dois idiomas — o problema é que o projeto
     * nunca teve lang/{locale}/validation.php, então TODA mensagem de
     * regra padrão do Laravel (required/date/numeric/etc., em qualquer
     * FormRequest do projeto, não só este) sempre caía no fallback
     * interno do framework, sempre em inglês, independente do locale.
     * Corrigido publicando lang/en/validation.php e lang/pt_BR/validation.php.
     */
    #[Test]
    public function validation_errors_are_returned_in_pt_br_when_requested(): void
    {
        $this->grantPermission('orders', 'update');

        $order = $this->createInstallmentOrder();
        $installments = $order['installments'];

        // Campo obrigatório ausente (due_date) — mensagem de regra padrão
        // do Laravel, não uma chave custom de messages.order.*.
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Accept-Language', 'pt_BR')
            ->putJson("/api/v1/orders/{$order['uuid']}/installments", [
                'installments' => [
                    ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 50],
                ],
            ]);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        // Chave de erro é uma string literal com pontos ("installments.0.due_date"),
        // não um caminho aninhado — acessa via array direto, não dot notation do json().
        $this->assertStringContainsString(
            'obrigatório',
            $response->json('errors')['installments.0.due_date'][0]
        );

        // Regra de negócio custom (installment_sum_mismatch) também em pt-BR.
        $response2 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Accept-Language', 'pt_BR')
            ->putJson("/api/v1/orders/{$order['uuid']}/installments", [
                'installments' => [
                    ['uuid' => $installments[0]['uuid'], 'installment_number' => 1, 'amount' => 40, 'due_date' => '2026-08-10'],
                    ['uuid' => $installments[1]['uuid'], 'installment_number' => 2, 'amount' => 40, 'due_date' => '2026-09-10'],
                ],
            ]);

        $response2->assertStatus(422);
        $this->assertStringContainsString('soma de todas as parcelas', $response2->json('message'));
    }
}
