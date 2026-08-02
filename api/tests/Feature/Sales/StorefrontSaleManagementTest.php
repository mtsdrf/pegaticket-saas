<?php

namespace Tests\Feature\Sales;

use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Tela dedicada de gestão de vendas online (/storefront-sales/*),
 * permissão própria `storefront-sales,{action}` — independente de
 * `sales,{action}`. Reaproveita o MESMO SaleService
 * (approve/reject/cancel/deliver). Ver .claude/memory/architecture-decisions.md.
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
        $this->grantPermission('storefront-sales', 'deliver');
        $this->grantPermission('storefront-sales', 'undeliver');
        $this->grantPermission('storefront-sales', 'pay');
        // 'create' pra poder criar o pedido de fixture via POST /sales normal.
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
        $order->save();

        return $order->fresh();
    }

    private function approve(Sale $order): void
    {
        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/approve')->assertStatus(200);
    }

    #[Test]
    public function full_happy_path_pending_approval_to_delivered(): void
    {
        $order = $this->createPendingApprovalOrder();

        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/approve')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/complete')
            ->assertStatus(200)
            ->assertJsonPath('data.is_completed', true);

        $this->assertTrue($order->fresh()->is_completed);
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

        // ...mas continua SEM acesso à rota genérica /sales/{order}/deliver.
        $this->auth()->patchJson('/api/v1/sales/' . $order->uuid . '/complete')->assertStatus(403);
    }

    #[Test]
    public function generic_sales_permission_does_not_grant_access_to_storefront_sales_screen(): void
    {
        $this->setUpTenantScopedUser('generic-sales-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'read');
        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'deliver');

        $order = $this->createPendingApprovalOrder();

        $this->auth()->getJson('/api/v1/storefront-sales')->assertStatus(403);
        $this->auth()->postJson('/api/v1/storefront-sales/' . $order->uuid . '/approve')->assertStatus(403);
    }

    /**
     * Tela genérica /pedidos (SaleListPage) passou a filtrar
     * origin=staff sempre — pedidos do canal online agora só aparecem em
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
    public function undeliver_route_reverts_delivery(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/complete')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/reopen')
            ->assertStatus(200)
            ->assertJsonPath('data.is_completed', false);

        $this->assertFalse($order->fresh()->is_completed);
    }

    #[Test]
    public function pay_route_registers_full_payment(): void
    {
        // Pedido de fixture: 3 un. x R$10 = R$30, não parcelado. Sem `amount`
        // no body, pay() faz pagamento INTEGRAL (is_paid=true), nunca parcial.
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);

        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/pay')
            ->assertStatus(200)
            ->assertJsonPath('data.is_paid', true);

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_paid);
        $this->assertSame((float) $fresh->total_amount, (float) $fresh->paid_amount);
    }

    #[Test]
    public function active_only_filter_returns_only_actionable_sales_in_priority_order(): void
    {
        // Mix de estados (criados nesta ordem, pending_approval por ÚLTIMO
        // pra provar que a ordenação fixa o coloca em 1º mesmo com id maior).
        $confirmed = $this->createPendingApprovalOrder();
        $this->approve($confirmed);

        $deliveredUnpaid = $this->createPendingApprovalOrder();
        $this->approve($deliveredUnpaid);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $deliveredUnpaid->uuid . '/complete')->assertStatus(200);

        // Concluído (pago + entregue) — deve ser EXCLUÍDO.
        $completed = $this->createPendingApprovalOrder();
        $this->approve($completed);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $completed->uuid . '/complete')->assertStatus(200);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $completed->uuid . '/pay')->assertStatus(200);

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

        $response->assertStatus(200)->assertJsonCount(3, 'data');

        $this->assertSame([
            $pending->uuid,
            $confirmed->uuid,
            $deliveredUnpaid->uuid,
        ], array_column($response->json('data'), 'uuid'));
    }

    #[Test]
    public function active_only_absent_keeps_default_listing(): void
    {
        // Sem o parâmetro, o filtro não se aplica — pedido concluído
        // (pago+entregue) continua aparecendo na listagem normal.
        $completed = $this->createPendingApprovalOrder();
        $this->approve($completed);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $completed->uuid . '/complete')->assertStatus(200);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $completed->uuid . '/pay')->assertStatus(200);

        $this->auth()->getJson('/api/v1/storefront-sales')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->auth()->getJson('/api/v1/storefront-sales?active_only=true')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function new_routes_require_their_own_permission_action(): void
    {
        // Usuário com as permissões antigas da tela, mas SEM as actions
        // novas (undeliver/pay) — as rotas novas dão 403.
        $this->setUpTenantScopedUser('storefront-partial-perms@test.com');
        $this->grantPermission('storefront-sales', 'read');
        $this->grantPermission('storefront-sales', 'approve');
        $this->grantPermission('storefront-sales', 'deliver');
        $this->grantPermission('sales', 'create');

        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/complete')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/reopen')->assertStatus(403);
        $this->auth()->patchJson('/api/v1/storefront-sales/' . $order->uuid . '/pay')->assertStatus(403);
    }
}
