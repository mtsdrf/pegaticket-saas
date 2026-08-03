<?php

namespace Tests\Feature\Sales;

use App\Models\Sale\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Tela dedicada de gestão de vendas online (/storefront-sales/*),
 * permissão própria `storefront-sales,{action}` — independente de
 * `sales,{action}`. Reaproveita o MESMO SaleService (approve/reject/cancel).
 * Não existe mais ação manual de "entregar"/"pagar" — pagamento de venda
 * online é sempre automático via retorno do PagBank. Ver
 * .claude/memory/architecture-decisions.md.
 */
class StorefrontSaleManagementTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('storefront-sales-user@test.com');
        $this->grantPermission('storefront-sales', 'read');
        $this->grantPermission('storefront-sales', 'approve');
        $this->grantPermission('storefront-sales', 'cancel');
        // 'create' pra poder criar a venda de fixture via POST /sales normal.
        $this->grantPermission('sales', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createPendingApprovalOrder(string $origin = 'storefront'): Sale
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $order = Sale::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $order->status = 'pending_approval';
        $order->origin = $origin;
        $order->is_paid = false;
        $order->paid_at = null;
        $order->save();

        return $order->fresh();
    }

    private function approve(Sale $order): void
    {
        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/approve')->assertStatus(200);
    }

    /** Simula a confirmação de pagamento que normalmente vem do webhook do PagBank. */
    private function markPaid(Sale $order): void
    {
        $order->forceFill(['is_paid' => true, 'paid_at' => now()])->save();
    }

    #[Test]
    public function approve_confirms_a_pending_order(): void
    {
        $order = $this->createPendingApprovalOrder();

        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/approve')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    #[Test]
    public function reject_changes_pending_order_to_rejected(): void
    {
        $order = $this->createPendingApprovalOrder();

        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/reject', [
            'reason' => 'Fora da área de entrega.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'rejected');

        $this->assertSame('rejected', $order->fresh()->status);
    }

    #[Test]
    public function cancel_cancels_a_confirmed_order(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);

        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/cancel', [
            'cancellation_reason' => 'Cliente desistiu.',
        ])->assertStatus(200);

        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    #[Test]
    public function index_only_returns_storefront_origin_sales_even_when_client_tries_to_override(): void
    {
        $storefrontOrder = $this->createPendingApprovalOrder('storefront');
        $this->createPendingApprovalOrder('staff');

        $response = $this->auth()->getJson('/api/v1/storefront-sales?origin=staff');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($storefrontOrder->uuid, $response->json('data.0.uuid'));
    }

    #[Test]
    public function storefront_sales_permission_is_independent_from_sales_permission(): void
    {
        // Usuário SEM nenhuma permissão sales,* (só storefront-sales,*
        // do setUp) consegue usar a tela nova normalmente...
        $order = $this->createPendingApprovalOrder();

        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/approve')->assertStatus(200);

        // ...mas continua SEM acesso à rota genérica /sales/{order}/cancel.
        $this->auth()->patchJson('/api/v1/sales/' . $order->uuid . '/cancel', [
            'cancellation_reason' => 'Teste',
        ])->assertStatus(403);
    }

    #[Test]
    public function generic_sales_permission_does_not_grant_access_to_storefront_sales_screen(): void
    {
        $this->setUpTenantScopedUser('generic-sales-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'read');
        $this->grantPermission('sales', 'update');

        $order = $this->createPendingApprovalOrder();

        $this->auth()->getJson('/api/v1/storefront-sales')->assertStatus(403);
        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/approve')->assertStatus(403);
    }

    /**
     * Tela genérica /vendas (SaleListPage) passou a filtrar
     * origin=staff sempre — vendas do canal online agora só aparecem em
     * /vendas-online. Filtro já existia no backend (SaleService::paginate),
     * só faltava o whitelist de SaleController::index() aceitar o
     * parâmetro.
     */
    #[Test]
    public function generic_sales_index_excludes_storefront_sales_when_filtered_by_origin_staff(): void
    {
        $this->grantPermission('sales', 'read');

        $staffOrder = $this->createPendingApprovalOrder('staff');
        $this->createPendingApprovalOrder('storefront');

        $response = $this->auth()->getJson('/api/v1/sales?origin=staff');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($staffOrder->uuid, $response->json('data.0.uuid'));
    }

    #[Test]
    public function active_only_filter_returns_only_unpaid_sales_in_priority_order(): void
    {
        // Mix de estados (criados nesta ordem, pending_approval por ÚLTIMO
        // pra provar que a ordenação fixa o coloca em 1º mesmo com id maior).
        $confirmed = $this->createPendingApprovalOrder();
        $this->approve($confirmed);

        // Pago — deve ser EXCLUÍDO (finalização automática via webhook).
        $paid = $this->createPendingApprovalOrder();
        $this->approve($paid);
        $this->markPaid($paid);

        // Recusado — EXCLUÍDO.
        $rejected = $this->createPendingApprovalOrder();
        $this->auth()->postJson('/api/v1/storefront-sales/' . $rejected->uuid . '/reject', [
            'reason' => 'Fora de área.',
        ])->assertStatus(200);

        // Cancelado — EXCLUÍDO.
        $cancelled = $this->createPendingApprovalOrder();
        $this->approve($cancelled);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $cancelled->uuid . '/cancel', [
            'cancellation_reason' => 'Desistiu.',
        ])->assertStatus(200);

        // pending_approval por último (maior id).
        $pending = $this->createPendingApprovalOrder();

        $response = $this->auth()->getJson('/api/v1/storefront-sales?active_only=true');

        $response->assertStatus(200)->assertJsonCount(2, 'data');

        $this->assertSame([
            $pending->uuid,
            $confirmed->uuid,
        ], array_column($response->json('data'), 'uuid'));
    }

    #[Test]
    public function active_only_absent_keeps_default_listing(): void
    {
        // Sem o parâmetro, o filtro não se aplica — venda paga continua
        // aparecendo na listagem normal.
        $paid = $this->createPendingApprovalOrder();
        $this->approve($paid);
        $this->markPaid($paid);

        $this->auth()->getJson('/api/v1/storefront-sales')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->auth()->getJson('/api/v1/storefront-sales?active_only=true')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
