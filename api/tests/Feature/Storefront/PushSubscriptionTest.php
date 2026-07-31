<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Storefront\PushSubscription;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Web Push real (VAPID), roadmap Delivery Fase 4 — última fatia.
 * POST /portal/push-subscriptions faz upsert por endpoint (o endpoint é
 * único por navegador/dispositivo, não por cliente).
 */
class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedCustomer(string $email): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'p256dh-key-value',
                'auth' => 'auth-key-value',
            ],
        ], $overrides);
    }

    #[Test]
    public function subscribes_successfully(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/push-subscriptions', $this->payload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('push_subscriptions', [
            'final_customer_id' => $customer->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'p256dh' => 'p256dh-key-value',
            'auth' => 'auth-key-value',
        ]);
    }

    #[Test]
    public function subscribing_the_same_endpoint_twice_upserts_instead_of_duplicating(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/push-subscriptions', $this->payload())
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/push-subscriptions', $this->payload([
                'keys' => ['p256dh' => 'new-p256dh', 'auth' => 'new-auth'],
            ]))
            ->assertStatus(201);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'final_customer_id' => $customer->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'p256dh' => 'new-p256dh',
            'auth' => 'new-auth',
        ]);
    }

    #[Test]
    public function reassigns_an_existing_endpoint_to_a_different_customer(): void
    {
        [, $tokenA] = $this->authenticatedCustomer('a@test.com');
        [$customerB, $tokenB] = $this->authenticatedCustomer('b@test.com');

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->postJson('/api/v1/portal/push-subscriptions', $this->payload())
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson('/api/v1/portal/push-subscriptions', $this->payload())
            ->assertStatus(201);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame(
            $customerB->id,
            PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/abc123')->firstOrFail()->final_customer_id
        );
    }

    #[Test]
    public function validates_required_fields(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/push-subscriptions', []);

        $response->assertStatus(422);
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->postJson('/api/v1/portal/push-subscriptions', $this->payload())
            ->assertStatus(401);
    }
}
