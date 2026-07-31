<?php

namespace Tests\Feature\Orders;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Tela dedicada de gestão de pedidos da loja (/storefront-orders/*),
 * permissão própria `storefront-orders,{action}` — independente de
 * `orders,{action}`. Reaproveita o MESMO OrderService (approve/reject/
 * cancel/deliver); dispatch() ("saiu para entrega") é o único conceito
 * novo. Ver .claude/memory/architecture-decisions.md.
 */
class StorefrontOrderManagementTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('storefront-orders-user@test.com');
        $this->grantPermission('storefront-orders', 'read');
        $this->grantPermission('storefront-orders', 'approve');
        $this->grantPermission('storefront-orders', 'cancel');
        $this->grantPermission('storefront-orders', 'dispatch');
        $this->grantPermission('storefront-orders', 'undispatch');
        $this->grantPermission('storefront-orders', 'deliver');
        $this->grantPermission('storefront-orders', 'undeliver');
        $this->grantPermission('storefront-orders', 'pay');
        // 'create' pra poder criar o pedido de fixture via POST /orders normal.
        $this->grantPermission('orders', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createPendingApprovalOrder(string $origin = 'storefront'): Order
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $order->status = 'pending_approval';
        $order->origin = $origin;
        $order->save();

        return $order->fresh();
    }

    private function approve(Order $order): void
    {
        $this->auth()->postJson('/api/v1/storefront-orders/' . $order->uuid . '/approve')->assertStatus(200);
    }

    #[Test]
    public function full_happy_path_pending_approval_to_delivered(): void
    {
        $order = $this->createPendingApprovalOrder();

        $this->auth()->postJson('/api/v1/storefront-orders/' . $order->uuid . '/approve')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')
            ->assertStatus(200)
            ->assertJsonPath('data.is_out_for_delivery', true);

        $this->assertNotNull($order->fresh()->out_for_delivery_at);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/deliver')
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', true);

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_out_for_delivery);
        $this->assertTrue($fresh->is_delivered);
    }

    #[Test]
    public function reject_changes_pending_order_to_rejected(): void
    {
        $order = $this->createPendingApprovalOrder();

        $this->auth()->postJson('/api/v1/storefront-orders/' . $order->uuid . '/reject', [
            'reason' => 'Fora da área de entrega.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'rejected');

        $this->assertSame('rejected', $order->fresh()->status);
    }

    #[Test]
    public function cancel_cancels_a_confirmed_order(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/cancel', [
            'cancellation_reason' => 'Cliente desistiu.',
        ])->assertStatus(200);

        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    #[Test]
    public function dispatch_fails_when_order_is_still_pending_approval(): void
    {
        $order = $this->createPendingApprovalOrder();

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->assertFalse($order->fresh()->is_out_for_delivery);
    }

    #[Test]
    public function dispatch_fails_when_already_dispatched(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function dispatch_fails_when_order_already_delivered(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/deliver')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function deliver_does_not_require_dispatch_first(): void
    {
        // origin=staff nunca passa por "saiu para entrega" — deliver()
        // continua indo direto, sem exigir dispatch() antes.
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/deliver')
            ->assertStatus(200)
            ->assertJsonPath('data.is_out_for_delivery', false);
    }

    #[Test]
    public function index_only_returns_storefront_origin_orders_even_when_client_tries_to_override(): void
    {
        $storefrontOrder = $this->createPendingApprovalOrder('storefront');
        $this->createPendingApprovalOrder('staff');

        $response = $this->auth()->getJson('/api/v1/storefront-orders?origin=staff');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($storefrontOrder->uuid, $response->json('data.0.uuid'));
    }

    #[Test]
    public function storefront_orders_permission_is_independent_from_orders_permission(): void
    {
        // Usuário SEM nenhuma permissão orders,* (só storefront-orders,*
        // do setUp) consegue usar a tela nova normalmente...
        $order = $this->createPendingApprovalOrder();

        $this->auth()->postJson('/api/v1/storefront-orders/' . $order->uuid . '/approve')->assertStatus(200);

        // ...mas continua SEM acesso à rota genérica /orders/{order}/deliver.
        $this->auth()->patchJson('/api/v1/orders/' . $order->uuid . '/deliver')->assertStatus(403);
    }

    #[Test]
    public function generic_orders_permission_does_not_grant_access_to_storefront_orders_screen(): void
    {
        $this->setUpTenantScopedUser('generic-orders-user@test.com');
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'read');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'deliver');

        $order = $this->createPendingApprovalOrder();

        $this->auth()->getJson('/api/v1/storefront-orders')->assertStatus(403);
        $this->auth()->postJson('/api/v1/storefront-orders/' . $order->uuid . '/approve')->assertStatus(403);
    }

    /**
     * Tela genérica /pedidos (OrderListPage) passou a filtrar
     * origin=staff sempre — pedidos da loja agora só aparecem em
     * /pedidos-loja. Filtro já existia no backend (OrderService::paginate),
     * só faltava o whitelist de OrderController::index() aceitar o
     * parâmetro.
     */
    #[Test]
    public function generic_orders_index_excludes_storefront_orders_when_filtered_by_origin_staff(): void
    {
        $this->grantPermission('orders', 'read');

        $staffOrder = $this->createPendingApprovalOrder('staff');
        $this->createPendingApprovalOrder('storefront');

        $response = $this->auth()->getJson('/api/v1/orders?origin=staff');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($staffOrder->uuid, $response->json('data.0.uuid'));
    }

    #[Test]
    public function undispatch_reverts_out_for_delivery(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/undispatch')
            ->assertStatus(200)
            ->assertJsonPath('data.is_out_for_delivery', false);

        $fresh = $order->fresh();
        $this->assertFalse($fresh->is_out_for_delivery);
        $this->assertNull($fresh->out_for_delivery_at);
    }

    #[Test]
    public function undispatch_fails_when_order_is_not_out_for_delivery(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/undispatch')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE')
            ->assertJsonPath('message', __('messages.order.not_out_for_delivery'));

        $this->assertFalse($order->fresh()->is_out_for_delivery);
    }

    #[Test]
    public function undispatch_fails_when_order_already_delivered(): void
    {
        // Dispatch + deliver deixa is_out_for_delivery=true E
        // is_delivered=true ao mesmo tempo (deliver não reseta o "saiu para
        // entrega") — é o único caminho que atinge o guard already_delivered.
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')->assertStatus(200);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/deliver')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/undispatch')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE')
            ->assertJsonPath('message', __('messages.order.already_delivered'));

        $this->assertTrue($order->fresh()->is_out_for_delivery);
    }

    #[Test]
    public function undispatch_fails_when_order_cancelled(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/cancel', [
            'cancellation_reason' => 'Cliente desistiu.',
        ])->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/undispatch')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE')
            ->assertJsonPath('message', __('messages.order.already_cancelled'));
    }

    #[Test]
    public function undeliver_route_reverts_delivery(): void
    {
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/deliver')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/undeliver')
            ->assertStatus(200)
            ->assertJsonPath('data.is_delivered', false);

        $this->assertFalse($order->fresh()->is_delivered);
    }

    #[Test]
    public function pay_route_registers_full_payment(): void
    {
        // Pedido de fixture: 3 un. x R$10 = R$30, não parcelado. Sem `amount`
        // no body, pay() faz pagamento INTEGRAL (is_paid=true), nunca parcial.
        $order = $this->createPendingApprovalOrder();
        $this->approve($order);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/pay')
            ->assertStatus(200)
            ->assertJsonPath('data.is_paid', true);

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_paid);
        $this->assertSame((float) $fresh->total_amount, (float) $fresh->paid_amount);
    }

    #[Test]
    public function active_only_filter_returns_only_actionable_orders_in_priority_order(): void
    {
        // Mix de estados (criados nesta ordem, pending_approval por ÚLTIMO
        // pra provar que a ordenação fixa o coloca em 1º mesmo com id maior).
        $confirmed = $this->createPendingApprovalOrder();
        $this->approve($confirmed);

        $dispatched = $this->createPendingApprovalOrder();
        $this->approve($dispatched);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $dispatched->uuid . '/dispatch')->assertStatus(200);

        $deliveredUnpaid = $this->createPendingApprovalOrder();
        $this->approve($deliveredUnpaid);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $deliveredUnpaid->uuid . '/deliver')->assertStatus(200);

        // Concluído (pago + entregue) — deve ser EXCLUÍDO.
        $completed = $this->createPendingApprovalOrder();
        $this->approve($completed);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $completed->uuid . '/deliver')->assertStatus(200);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $completed->uuid . '/pay')->assertStatus(200);

        // Recusado — EXCLUÍDO.
        $rejected = $this->createPendingApprovalOrder();
        $this->auth()->postJson('/api/v1/storefront-orders/' . $rejected->uuid . '/reject', [
            'reason' => 'Fora de área.',
        ])->assertStatus(200);

        // Cancelado — EXCLUÍDO.
        $cancelled = $this->createPendingApprovalOrder();
        $this->approve($cancelled);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $cancelled->uuid . '/cancel', [
            'cancellation_reason' => 'Desistiu.',
        ])->assertStatus(200);

        // pending_approval por último (maior id).
        $pending = $this->createPendingApprovalOrder();

        $response = $this->auth()->getJson('/api/v1/storefront-orders?active_only=true');

        $response->assertStatus(200)->assertJsonCount(4, 'data');

        $this->assertSame([
            $pending->uuid,
            $confirmed->uuid,
            $dispatched->uuid,
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
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $completed->uuid . '/deliver')->assertStatus(200);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $completed->uuid . '/pay')->assertStatus(200);

        $this->auth()->getJson('/api/v1/storefront-orders')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->auth()->getJson('/api/v1/storefront-orders?active_only=true')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function new_routes_require_their_own_permission_action(): void
    {
        // Usuário com as permissões antigas da tela, mas SEM as actions
        // novas (undispatch/undeliver/pay) — as 3 rotas novas dão 403.
        $this->setUpTenantScopedUser('storefront-partial-perms@test.com');
        $this->grantPermission('storefront-orders', 'read');
        $this->grantPermission('storefront-orders', 'approve');
        $this->grantPermission('storefront-orders', 'dispatch');
        $this->grantPermission('storefront-orders', 'deliver');
        $this->grantPermission('orders', 'create');

        $order = $this->createPendingApprovalOrder();
        $this->approve($order);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/dispatch')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/undispatch')->assertStatus(403);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/undeliver')->assertStatus(403);
        $this->auth()->patchJson('/api/v1/storefront-orders/' . $order->uuid . '/pay')->assertStatus(403);
    }
}
