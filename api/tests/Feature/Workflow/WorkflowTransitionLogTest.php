<?php

namespace Tests\Feature\Workflow;

use App\Models\Order\Order;
use App\Models\Workflow\WorkflowTransitionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class WorkflowTransitionLogTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('workflow-user@test.com');
        $this->grantPermission('orders', 'read');
        $this->grantPermission('orders', 'create');
        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'deliver');
        $this->grantPermission('storefront-orders', 'read');
        $this->grantPermission('stock', 'entry');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function order_approval_creates_operational_transition_log(): void
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);
        $this->stockEntry($this->tenant->id, $product, $location, 30);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'final_customer_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'origin' => 'storefront',
            'status' => 'pending_approval',
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201);

        $orderUuid = $response->json('data.uuid');
        $order = Order::query()->where('uuid', $orderUuid)->firstOrFail();
        $order->status = 'pending_approval';
        $order->origin = 'storefront';
        $order->save();

        $this->auth()->postJson("/api/v1/orders/{$orderUuid}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $log = WorkflowTransitionLog::query()
            ->where('workflow_type', 'order')
            ->where('entity_uuid', $orderUuid)
            ->where('transition_type', 'move')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->tenant->id, $log->tenant_id);
        $this->assertSame($this->userId, $log->user_id);
        $this->assertSame('approval', $log->from_stage);
        $this->assertSame('production', $log->to_stage);
    }

    #[Test]
    public function order_timeline_endpoint_returns_operational_history(): void
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 32]);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $response = $this->auth()->postJson('/api/v1/orders', [
            'final_customer_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $orderUuid = $response->json('data.uuid');

        $this->auth()->patchJson("/api/v1/orders/{$orderUuid}/deliver")
            ->assertStatus(200);

        $timeline = $this->auth()->getJson("/api/v1/orders/{$orderUuid}/workflow-transitions")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('messages.workflow.timeline_list'));

        $this->assertNotEmpty($timeline->json('data'));
        $this->assertSame($orderUuid, $timeline->json('data.0.entity_uuid'));
        $this->assertSame('order', $timeline->json('data.0.workflow_type'));
        $this->assertSame('financial_pending', $timeline->json('data.0.to_stage'));
        $this->assertSame('workflow-user@test.com', $timeline->json('data.0.user.email'));
    }

}
