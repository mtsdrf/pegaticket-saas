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
 * Fila de aprovação do staff (Delivery Fase 1) — POST /sales/{order}/approve
 * e /sales/{order}/reject, extensão do SaleController/SaleService
 * existentes (não um controller/service novo). Todo pedido origin=storefront
 * nasce status=pending_approval e precisa passar por aqui.
 */
class SaleApprovalQueueTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('approval-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'deliver');
        $this->grantPermission('sales', 'pay');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Cria um pedido de verdade via POST /sales (reserva de estoque real,
     * origin=staff/status=confirmed por default) e simula que ele "nasceu"
     * da loja, virando pending_approval — mesmo estado que
     * StorefrontCheckoutService produz, só que aqui com uma reserva real
     * verdade em reject().
     */
    private function createPendingApprovalOrderWithRealReservation(): Sale
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
        $order->origin = 'storefront';
        $order->save();

        return $order->fresh();
    }

    private function createPendingApprovalOrderWithoutReservation(): Sale
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id);

        $order = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 30,
            'is_paid' => false,
            'is_completed' => false,
            'status' => 'pending_approval',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $order->id,
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

        $response = $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve');

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

        $response = $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve');

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    #[Test]
    public function reject_changes_status_to_rejected_and_does_not_touch_cancelled_at(): void
    {
        $order = $this->createPendingApprovalOrderWithRealReservation();

        $response = $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/reject', [
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
    public function reject_changes_status_when_order_is_pending_approval(): void
    {
        $order = $this->createPendingApprovalOrderWithoutReservation();

        $response = $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/reject', [
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

        $response = $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/reject', [
            'reason' => 'Qualquer motivo',
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

}
