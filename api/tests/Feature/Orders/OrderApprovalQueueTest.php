<?php

namespace Tests\Feature\Orders;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Stock\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Fila de aprovação do staff (Delivery Fase 1) — POST /orders/{order}/approve
 * e /orders/{order}/reject, extensão do OrderController/OrderService
 * existentes (não um controller/service novo). Todo pedido origin=storefront
 * nasce status=pending_approval e precisa passar por aqui.
 */
class OrderApprovalQueueTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('approval-user@test.com');
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'deliver');
        $this->grantPermission('orders', 'pay');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Cria um pedido de verdade via POST /orders (reserva de estoque real,
     * origin=staff/status=confirmed por default) e simula que ele "nasceu"
     * da loja, virando pending_approval — mesmo estado que
     * StorefrontCheckoutService produz, só que aqui com uma reserva real
     * de StockMovement já ligada ao item, pra exercitar reserveCancel() de
     * verdade em reject().
     */
    private function createPendingApprovalOrderWithRealReservation(): Order
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'final_customer_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $order->status = 'pending_approval';
        $order->origin = 'storefront';
        $order->save();

        return $order->fresh();
    }

    private function createPendingApprovalOrderWithoutReservation(): Order
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);
        $product = $this->createProduct($this->tenant->id);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 30,
            'is_paid' => false,
            'is_delivered' => false,
            'status' => 'pending_approval',
            'origin' => 'storefront',
            'stock_reserved' => false,
        ]);

        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'ticket_type_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10,
            'line_total' => 30,
        ]);

        return $order;
    }

    #[Test]
    public function approve_changes_status_from_pending_approval_to_confirmed(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();

        $response = $this->auth()->postJson('/api/v1/orders/' . $order->uuid . '/approve');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    #[Test]
    public function approve_fails_when_order_is_not_pending_approval(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();
        $order->status = 'confirmed';
        $order->save();

        $response = $this->auth()->postJson('/api/v1/orders/' . $order->uuid . '/approve');

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    #[Test]
    public function reject_changes_status_to_rejected_and_does_not_touch_cancelled_at(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();

        $response = $this->auth()->postJson('/api/v1/orders/' . $order->uuid . '/reject', [
            'reason' => 'Fora da área de entrega.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.cancellation_reason', 'Fora da área de entrega.')
            ->assertJsonPath('data.cancelled_at', null);

        $fresh = $order->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertNull($fresh->cancelled_at);
        $this->assertSame('Fora da área de entrega.', $fresh->cancellation_reason);
    }

    #[Test]
    public function reject_releases_stock_reservation_when_order_had_one(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();
        $order->load('items', 'stockLocation');
        $item = $order->items->first();

        $before = StockBalance::where('ticket_type_id', $item->ticket_type_id)
            ->where('location_id', $order->stock_location_id)
            ->firstOrFail();
        $this->assertEquals(3, $before->quantity_reserved);

        $this->auth()->postJson('/api/v1/orders/' . $order->uuid . '/reject', [
            'reason' => 'Estoque redirecionado.',
        ])->assertStatus(200);

        $after = StockBalance::where('ticket_type_id', $item->ticket_type_id)
            ->where('location_id', $order->stock_location_id)
            ->firstOrFail();
        $this->assertEquals(0, $after->quantity_reserved);
    }

    #[Test]
    public function reject_does_not_touch_stock_when_stock_reserved_is_false(): void
    {
        $order = $this->createPendingApprovalOrderWithoutReservation();

        $response = $this->auth()->postJson('/api/v1/orders/' . $order->uuid . '/reject', [
            'reason' => null,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'rejected');
        $this->assertSame('rejected', $order->fresh()->status);
    }

    #[Test]
    public function reject_fails_when_order_is_not_pending_approval(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();
        $order->status = 'confirmed';
        $order->save();

        $response = $this->auth()->postJson('/api/v1/orders/' . $order->uuid . '/reject', [
            'reason' => 'Qualquer motivo',
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /**
     * Achado de code review: antes desta correção, deliver()/pay() não
     * checavam `status`, então um pedido pending_approval (nunca visto pelo
     * staff) podia ser entregue/pago direto pela API normal de pedidos,
     * contornando a fila de aprovação por completo — inclusive causando
     * saída física de estoque pra um pedido com stock_reserved=false que
     * nunca foi aprovado.
     */
    #[Test]
    public function deliver_fails_when_order_is_pending_approval(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();

        $response = $this->auth()->patchJson('/api/v1/orders/' . $order->uuid . '/deliver');

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertFalse($order->fresh()->is_delivered);
    }

    #[Test]
    public function pay_fails_when_order_is_pending_approval(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();

        $response = $this->auth()->patchJson('/api/v1/orders/' . $order->uuid . '/pay');

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertFalse($order->fresh()->is_paid);
    }

    #[Test]
    public function deliver_fails_when_order_was_rejected(): void
    {
        $order = $this->createPendingApprovalOrderWithoutReservation();
        $order->status = 'rejected';
        $order->save();

        $response = $this->auth()->patchJson('/api/v1/orders/' . $order->uuid . '/deliver');

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertFalse($order->fresh()->is_delivered);
    }

    #[Test]
    public function deliver_succeeds_normally_after_approval(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();
        $this->auth()->postJson('/api/v1/orders/' . $order->uuid . '/approve')->assertStatus(200);

        $response = $this->auth()->patchJson('/api/v1/orders/' . $order->uuid . '/deliver');

        $response->assertStatus(200);
        $this->assertTrue($order->fresh()->is_delivered);
    }
}
