<?php

namespace Tests\Feature\CashSession;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Caixa (roadmap Fase 2 — bilheteria presencial, "caixa e estações de
 * venda"): abertura/fechamento com contagem, valor esperado calculado a
 * partir das vendas manuais em dinheiro feitas dentro da janela aberta.
 */
class CashSessionTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('cash-session@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function opens_a_cash_session_with_the_opening_amount(): void
    {
        $this->grantPermission('cash_sessions', 'open');

        $response = $this->auth()->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 100,
            'opening_notes' => 'Troco inicial',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'aberto')
            ->assertJsonPath('data.opening_amount', '100.00');
    }

    #[Test]
    public function cannot_open_a_second_session_while_one_is_already_open(): void
    {
        $this->grantPermission('cash_sessions', 'open');

        $this->auth()->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 100])
            ->assertStatus(201);

        $this->auth()->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 50])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_CASH_SESSION_STATE');
    }

    #[Test]
    public function cannot_close_when_there_is_no_open_session(): void
    {
        $this->grantPermission('cash_sessions', 'close');

        $this->auth()->postJson('/api/v1/cash-sessions/close', ['closing_amount' => 100])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_CASH_SESSION_STATE');
    }

    #[Test]
    public function current_reflects_expected_cash_amount_from_manual_cash_sales(): void
    {
        $this->grantPermission('cash_sessions', 'open');
        $this->grantPermission('cash_sessions', 'read');
        $this->grantPermission('sales', 'create');

        $this->auth()->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 100])
            ->assertStatus(201);

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'payment_method' => 'cash',
            'items' => [['ticket_type_uuid' => $product->uuid, 'quantity' => 2]],
        ])->assertStatus(201);

        $current = $this->auth()->getJson('/api/v1/cash-sessions/current')
            ->assertStatus(200)
            ->json('data');

        // 100 abertura + 60 (2x30) da venda manual em dinheiro já paga.
        $this->assertSame('160.00', $current['expected_cash_amount']);
    }

    #[Test]
    public function closing_computes_the_difference_against_the_expected_amount(): void
    {
        $this->grantPermission('cash_sessions', 'open');
        $this->grantPermission('cash_sessions', 'close');

        $this->auth()->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 100])
            ->assertStatus(201);

        $response = $this->auth()->postJson('/api/v1/cash-sessions/close', [
            'closing_amount' => 95,
            'closing_notes' => 'Faltaram 5 reais',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'fechado')
            ->assertJsonPath('data.closing_amount', '95.00')
            ->assertJsonPath('data.expected_cash_amount', '100.00')
            ->assertJsonPath('data.difference_amount', '-5.00');
    }

    #[Test]
    public function a_second_session_can_be_opened_after_the_first_is_closed(): void
    {
        $this->grantPermission('cash_sessions', 'open');
        $this->grantPermission('cash_sessions', 'close');

        $this->auth()->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 100])->assertStatus(201);
        $this->auth()->postJson('/api/v1/cash-sessions/close', ['closing_amount' => 100])->assertStatus(200);

        $this->auth()->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 50])
            ->assertStatus(201);
    }
}
