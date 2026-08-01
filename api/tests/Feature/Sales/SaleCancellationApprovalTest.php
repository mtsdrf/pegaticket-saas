<?php

namespace Tests\Feature\Orders;

use App\Models\Sale\Sale;
use App\Models\Stock\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Aprovação/rejeição, pelo staff, da solicitação de cancelamento feita
 * pelo cliente final via Portal (roadmap A4 — "aprovar cancelamento").
 * POST /orders/{order}/approve-cancellation e /reject-cancellation.
 */
class SaleCancellationApprovalTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('cancellation-approval-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Cria um pedido real via POST /orders (reserva de estoque real),
     * marca origin=storefront e simula que o cliente já solicitou o
     * cancelamento (status=cancellation_requested,
     * status_before_cancellation_request='confirmed').
     */
    private function createOrderWithCancellationRequested(): Sale
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $order = Sale::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $order->origin = 'storefront';
        $order->status = 'cancellation_requested';
        $order->status_before_cancellation_request = 'confirmed';
        $order->cancellation_reason = 'Pedido errado';
        $order->save();

        return $order->fresh();
    }

    #[Test]
    public function staff_approves_cancellation_request_and_executes_the_cancellation(): void
    {
        $order = $this->createOrderWithCancellationRequested();

        $response = $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve-cancellation');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.cancellation_reason', 'Pedido errado');

        $order->refresh();

        $this->assertNotNull($order->cancelled_at);
        $this->assertEquals('confirmed', $order->status);
        $this->assertNull($order->status_before_cancellation_request);

        $this->assertDatabaseHas('audit_logs', ['event' => 'order_cancellation_approved']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'order_cancelled']);
    }

    #[Test]
    public function staff_rejects_cancellation_request_and_reverts_status(): void
    {
        $order = $this->createOrderWithCancellationRequested();

        $response = $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/reject-cancellation');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.cancellation_reason', null);

        $order->refresh();

        $this->assertNull($order->cancelled_at);
        $this->assertEquals('confirmed', $order->status);
        $this->assertNull($order->status_before_cancellation_request);
        $this->assertNull($order->cancellation_reason);

        $this->assertDatabaseHas('audit_logs', ['event' => 'order_cancellation_rejected']);
    }

    #[Test]
    public function approving_cancellation_fails_when_no_request_is_open(): void
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $order = Sale::where('uuid', $response->json('data.uuid'))->firstOrFail();

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve-cancellation')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/reject-cancellation')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }
}
