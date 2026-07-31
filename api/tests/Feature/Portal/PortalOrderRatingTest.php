<?php

namespace Tests\Feature\Portal;

use App\Models\Client\Client;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Avaliação de pedido entregue (roadmap Delivery, Fase 4 — retenção):
 * POST /portal/orders/{uuid}/rating. Exige is_delivered=true, 1 avaliação
 * por pedido, e a mesma checagem de posse do reorder.
 */
class PortalOrderRatingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

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

    private function createOrder(Tenant $tenant, Client $client, array $overrides = []): Order
    {
        $location = $this->createLocation($tenant->id);

        return Order::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
        ], $overrides));
    }

    private function linkCustomerToOrder(string $token, string $orderUuid): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $orderUuid])
            ->assertStatus(200);
    }

    #[Test]
    public function rates_a_delivered_order_successfully(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client, ['is_delivered' => true, 'delivered_at' => now()]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/orders/' . $order->uuid . '/rating', [
                'rating' => 5,
                'comment' => 'Ótimo atendimento!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Ótimo atendimento!');

        $this->assertDatabaseHas('order_ratings', [
            'order_id' => $order->id,
            'tenant_id' => $tenant->id,
            'rating' => 5,
        ]);
    }

    #[Test]
    public function rejects_rating_when_order_is_not_delivered_yet(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client, ['is_delivered' => false]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/orders/' . $order->uuid . '/rating', ['rating' => 5]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->assertDatabaseMissing('order_ratings', ['order_id' => $order->id]);
    }

    #[Test]
    public function rejects_second_rating_for_the_same_order_with_a_clear_message(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client, ['is_delivered' => true, 'delivered_at' => now()]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/orders/' . $order->uuid . '/rating', ['rating' => 5])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/orders/' . $order->uuid . '/rating', ['rating' => 1]);

        $response->assertStatus(422)->assertJsonPath('code', 'ORDER_ALREADY_RATED');

        $this->assertDatabaseCount('order_ratings', 1);
    }

    #[Test]
    public function returns_404_for_order_belonging_to_another_customer(): void
    {
        [, $tokenA] = $this->authenticatedCustomer('a@test.com');
        [, $tokenB] = $this->authenticatedCustomer('b@test.com');

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client, ['is_delivered' => true, 'delivered_at' => now()]);

        $this->linkCustomerToOrder($tokenA, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson('/api/v1/portal/orders/' . $order->uuid . '/rating', ['rating' => 3])
            ->assertStatus(404);

        $this->assertDatabaseMissing('order_ratings', ['order_id' => $order->id]);
    }

    #[Test]
    public function validates_rating_is_between_one_and_five(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client, ['is_delivered' => true, 'delivered_at' => now()]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/orders/' . $order->uuid . '/rating', ['rating' => 6])
            ->assertStatus(422);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->postJson('/api/v1/portal/orders/' . Str::uuid() . '/rating', ['rating' => 5])
            ->assertStatus(401);
    }
}
