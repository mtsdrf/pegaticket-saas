<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Solicitação de cancelamento pelo cliente final (roadmap A4 — "aprovar
 * cancelamento"): POST /portal/sales/{uuid}/request-cancellation. Só
 * pedido origin=storefront, só enquanto não saiu para entrega/entregue.
 */
class PortalSaleCancellationRequestTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSaleFixtures;

    private function authenticatedCustomer(string $email): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant ' . Str::random(6),
            'slug' => 'tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function createOrder(Tenant $tenant, FinalCustomer $owner, array $overrides = []): Sale
    {

        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $owner->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
            'status' => 'confirmed',
            'origin' => 'storefront',
        ], $overrides));
    }

    private function linkCustomerToOrder(string $token, string $orderUuid): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $orderUuid])
            ->assertStatus(200);
    }

    #[Test]
    public function customer_requests_cancellation_successfully(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', [
                'reason' => 'Pedido errado',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancellation_requested');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancellation_requested',
            'status_before_cancellation_request' => 'confirmed',
            'cancellation_reason' => 'Pedido errado',
            'cancelled_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'order_cancellation_requested',
            'user_id' => null,
        ]);
    }

    #[Test]
    public function customer_can_request_cancellation_without_a_reason(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente2@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', []);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancellation_requested');
    }

    #[Test]
    public function rejects_request_when_order_already_out_for_delivery(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente3@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer, [
            'is_out_for_delivery' => true,
            'out_for_delivery_at' => now(),
        ]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', []);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    #[Test]
    public function rejects_request_when_order_already_delivered(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente4@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer, [
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', []);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function rejects_request_when_order_origin_is_not_storefront(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente5@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer, ['origin' => 'staff']);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', []);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    #[Test]
    public function rejects_request_for_an_order_not_owned_by_the_customer(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente6@test.com');
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client);

        // Sem link confirmado (não chamou linkCustomerToOrder).
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', []);

        $response->assertStatus(404);
    }

    #[Test]
    public function rejects_second_cancellation_request_for_the_same_order(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente7@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer);

        $this->linkCustomerToOrder($token, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', [])
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/request-cancellation', []);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }
}
