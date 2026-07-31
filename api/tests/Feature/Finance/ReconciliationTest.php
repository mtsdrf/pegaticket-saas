<?php

namespace Tests\Feature\Finance;

use App\Models\Client\Client;
use App\Models\Order\Order;
use App\Models\Stock\StockLocation;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
use App\Models\Subscription\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * GET /finance/reconciliation (roadmap A3.12) — leitura agregada de
 * payments/refunds/webhook_events, tenant-scoped via payments.payable=Order.
 */
class ReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('finance-user@test.com');
        $this->grantPermission('finance', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function createOrderForTenant(int $tenantId): Order
    {
        $client = $this->createClient($tenantId);
        $location = $this->createLocation($tenantId);

        return Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'codigo' => 'PED-' . Str::random(6),
            'total_amount' => 100.0,
            'is_paid' => false,
            'is_delivered' => false,
        ]);
    }

    protected function createPaymentForOrder(Order $order, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'provider' => 'manual',
            'provider_charge_id' => 'charge-' . Str::random(8),
            'method' => 'pix',
            'amount' => 100.0,
            'status' => 'paid',
            'paid_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function lists_payments_scoped_to_the_current_tenant(): void
    {
        $order = $this->createOrderForTenant($this->tenant->id);
        $payment = $this->createPaymentForOrder($order);

        $otherTenant = \App\Models\Tenant\Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
        ]);
        $otherOrder = $this->createOrderForTenant($otherTenant->id);
        $this->createPaymentForOrder($otherOrder);

        $response = $this->auth()->getJson('/api/v1/finance/reconciliation');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $payment->uuid)
            ->assertJsonPath('data.0.order.uuid', $order->uuid);
    }

    #[Test]
    public function includes_refunds_and_filters_by_status(): void
    {
        $order = $this->createOrderForTenant($this->tenant->id);
        $paidPayment = $this->createPaymentForOrder($order, ['status' => 'paid']);
        $failedPayment = $this->createPaymentForOrder($order, ['status' => 'failed']);

        Refund::create([
            'uuid' => (string) Str::uuid(),
            'payment_id' => $paidPayment->id,
            'reason' => 'Cliente desistiu',
            'amount' => 30.0,
            'type' => 'partial',
            'protocol' => 'PROT-' . Str::random(8),
            'status' => 'done',
        ]);

        $response = $this->auth()->getJson('/api/v1/finance/reconciliation?status=paid');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $paidPayment->uuid)
            ->assertJsonCount(1, 'data.0.refunds')
            ->assertJsonPath('data.0.refunds.0.amount', 30);
    }

    #[Test]
    public function matches_webhook_event_by_provider_charge_id(): void
    {
        $order = $this->createOrderForTenant($this->tenant->id);
        $payment = $this->createPaymentForOrder($order, ['provider_charge_id' => 'charge-abc']);

        WebhookEvent::create([
            'provider' => 'manual',
            'external_id' => 'evt-1',
            'payload' => ['provider_charge_id' => 'charge-abc'],
            'processed_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/finance/reconciliation');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.webhook_event.external_id', 'evt-1');
    }

    #[Test]
    public function summary_groups_amount_by_status(): void
    {
        $order = $this->createOrderForTenant($this->tenant->id);
        $this->createPaymentForOrder($order, ['status' => 'paid', 'amount' => 50.0]);
        $this->createPaymentForOrder($order, ['status' => 'paid', 'amount' => 25.0]);
        $this->createPaymentForOrder($order, ['status' => 'failed', 'amount' => 10.0]);

        $response = $this->auth()->getJson('/api/v1/finance/reconciliation/summary');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $byStatus = collect($response->json('data.by_status'))->keyBy('status');
        $this->assertEquals(75.0, $byStatus['paid']['amount']);
        $this->assertEquals(2, $byStatus['paid']['count']);
        $this->assertEquals(10.0, $byStatus['failed']['amount']);
    }

    #[Test]
    public function denies_access_without_permission(): void
    {
        $user = \App\Models\User\User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'No Perm User',
            'email' => 'no-perm-finance@test.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'no-perm-finance@test.com',
            'password' => 'password123',
        ])->json('data');

        \App\Models\Tenant\TenantUser::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'tenant_role_id' => \App\Models\Tenant\TenantRole::where('tenant_id', $this->tenant->id)->first()->id,
            'is_active' => true,
        ]);

        $switch = $this->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/auth/switch-tenant', ['tenant_uuid' => $this->tenant->uuid])
            ->json('data');

        $this->withHeader('Authorization', 'Bearer ' . $switch['access_token'])
            ->getJson('/api/v1/finance/reconciliation')
            ->assertStatus(403);
    }
}
