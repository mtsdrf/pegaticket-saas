<?php

namespace Tests\Feature\Workflow;

use App\Models\Sale\Sale;
use App\Models\Workflow\WorkflowTransitionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class WorkflowTransitionLogTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('workflow-user@test.com');
        $this->grantPermission('sales', 'read');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
        $this->grantPermission('storefront-sales', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function order_approval_creates_operational_transition_log(): void
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'origin' => 'storefront',
            'status' => 'pending_approval',
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201);

        $saleUuid = $response->json('data.uuid');
        $order = Sale::query()->where('uuid', $saleUuid)->firstOrFail();
        $order->status = 'pending_approval';
        $order->origin = 'storefront';
        $order->is_paid = false;
        $order->paid_at = null;
        $order->save();

        $this->auth()->postJson("/api/v1/sales/{$saleUuid}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $log = WorkflowTransitionLog::query()
            ->where('workflow_type', 'sale')
            ->where('entity_uuid', $saleUuid)
            ->where('transition_type', 'move')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->tenant->id, $log->tenant_id);
        $this->assertSame($this->userId, $log->user_id);
        $this->assertSame('approval', $log->from_stage);
        $this->assertSame('confirmed', $log->to_stage);
    }

    #[Test]
    public function order_timeline_endpoint_returns_operational_history(): void
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 32]);

        // Venda manual não parcelada nasce já paga — a própria criação já
        // dispara a transição de pagamento (SalePaid), sem ação manual.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $saleUuid = $response->json('data.uuid');

        $timeline = $this->auth()->getJson("/api/v1/sales/{$saleUuid}/workflow-transitions")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('messages.workflow.timeline_list'));

        $this->assertNotEmpty($timeline->json('data'));
        $this->assertSame($saleUuid, $timeline->json('data.0.entity_uuid'));
        $this->assertSame('sale', $timeline->json('data.0.workflow_type'));
        $this->assertSame('completed', $timeline->json('data.0.to_stage'));
        $this->assertSame('workflow-user@test.com', $timeline->json('data.0.user.email'));
    }

}
