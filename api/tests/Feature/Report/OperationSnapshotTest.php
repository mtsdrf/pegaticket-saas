<?php

namespace Tests\Feature\Report;

use App\Models\Inventory\InventoryHold;
use App\Models\Sale\Sale;
use App\Models\Storefront\VirtualQueueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Dashboard operacional em tempo quase real (roadmap Fase 2) —
 * GET /reports/operation-snapshot agrega caixa, fila de aprovação e vendas
 * do dia num único round-trip.
 */
class OperationSnapshotTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('operation-snapshot@test.com');
        $this->grantPermission('dashboard', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function returns_null_cash_session_when_none_is_open(): void
    {
        $response = $this->auth()->getJson('/api/v1/reports/operation-snapshot');

        $response->assertStatus(200)
            ->assertJsonPath('data.cash_session', null)
            ->assertJsonPath('data.sales_pending_approval_count', 0);
    }

    #[Test]
    public function reflects_the_open_cash_session_and_todays_paid_sale(): void
    {
        $this->grantPermission('cash_sessions', 'open');
        $this->grantPermission('sales', 'create');

        $this->auth()->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 100])
            ->assertStatus(201);

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 40]);

        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [['ticket_type_uuid' => $product->uuid, 'quantity' => 1]],
        ])->assertStatus(201);

        $response = $this->auth()->getJson('/api/v1/reports/operation-snapshot');

        $response->assertStatus(200)
            ->assertJsonPath('data.cash_session.status', 'aberto')
            ->assertJsonPath('data.cash_session.expected_cash_amount', '100.00')
            ->assertJsonPath('data.sales_today.count', 1)
            ->assertJsonPath('data.sales_today.total_amount', '40.00');
    }

    #[Test]
    public function counts_sales_pending_approval(): void
    {
        $this->grantPermission('sales', 'read');
        $this->grantPermission('storefront-sales', 'read');

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $sale = Sale::create([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 20,
            'is_paid' => false,
            'status' => 'pending_approval',
            'origin' => 'storefront',
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/operation-snapshot');

        $response->assertStatus(200)->assertJsonPath('data.sales_pending_approval_count', 1);

        $this->assertNotNull($sale->id);
    }

    #[Test]
    public function reports_checkout_error_rate_from_the_hold_funnel(): void
    {
        $product = $this->createProduct($this->tenant->id, ['price' => 40]);
        $event = $product->event;

        InventoryHold::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'session_token' => 'sess-'.Str::random(8),
            'status' => InventoryHold::STATUS_CONVERTED,
            'origin' => 'storefront',
            'expires_at' => now()->addMinutes(15),
        ]);

        InventoryHold::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'session_token' => 'sess-'.Str::random(8),
            'status' => InventoryHold::STATUS_EXPIRED,
            'origin' => 'storefront',
            'expires_at' => now()->subMinutes(5),
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/operation-snapshot');

        $response->assertStatus(200)
            ->assertJsonPath('data.checkout.started', 2)
            ->assertJsonPath('data.checkout.completed', 1)
            ->assertJsonPath('data.checkout.error_rate_percent', 50);
    }

    #[Test]
    public function reports_the_current_virtual_queue_size(): void
    {
        $product = $this->createProduct($this->tenant->id, ['price' => 40]);
        $event = $product->event;
        $event->update(['high_demand_mode' => true]);

        VirtualQueueEntry::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'session_token' => 'sess-waiting',
            'position' => 1,
            'status' => VirtualQueueEntry::STATUS_WAITING,
        ]);

        VirtualQueueEntry::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'session_token' => 'sess-admitted',
            'position' => 2,
            'status' => VirtualQueueEntry::STATUS_ADMITTED,
            'admitted_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/operation-snapshot');

        $response->assertStatus(200)
            ->assertJsonPath('data.virtual_queue.waiting', 1)
            ->assertJsonPath('data.virtual_queue.admitted', 1);
    }
}
