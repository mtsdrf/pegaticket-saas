<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\CartEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * POST /loja/{slug}/eventos-carrinho (roadmap A3.14) — 100% público.
 */
class CartEventTest extends TestCase
{
    use RefreshDatabase;
    use CreatesStorefrontFixtures;

    #[Test]
    public function records_cart_abandoned_event_for_a_valid_store(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $response = $this->postJson('/api/v1/loja/' . $tenant->slug . '/eventos-carrinho', [
            'session_id' => 'anon-session-123',
            'event_type' => 'cart_abandoned',
            'items' => [
                ['product_uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Produto X', 'quantity' => 2, 'unit_price' => 10.5],
            ],
            'total_amount' => 21.0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event_type', 'cart_abandoned');

        $this->assertDatabaseHas('cart_events', [
            'tenant_id' => $tenant->id,
            'session_id' => 'anon-session-123',
            'event_type' => 'cart_abandoned',
        ]);

        $event = CartEvent::where('tenant_id', $tenant->id)->first();
        $this->assertEquals(21.0, $event->payload['total_amount']);
        $this->assertCount(1, $event->payload['items']);
    }

    #[Test]
    public function returns_404_for_unknown_store_slug(): void
    {
        $this->postJson('/api/v1/loja/loja-inexistente/eventos-carrinho', [
            'session_id' => 'anon-session-123',
            'event_type' => 'cart_abandoned',
        ])->assertStatus(404);
    }

    #[Test]
    public function returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);

        $this->postJson('/api/v1/loja/' . $tenant->slug . '/eventos-carrinho', [
            'session_id' => 'anon-session-123',
            'event_type' => 'cart_abandoned',
        ])->assertStatus(404);
    }

    #[Test]
    public function rejects_invalid_event_type(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $this->postJson('/api/v1/loja/' . $tenant->slug . '/eventos-carrinho', [
            'session_id' => 'anon-session-123',
            'event_type' => 'not_a_real_event',
        ])->assertStatus(422);
    }

    #[Test]
    public function rejects_missing_session_id(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $this->postJson('/api/v1/loja/' . $tenant->slug . '/eventos-carrinho', [
            'event_type' => 'cart_abandoned',
        ])->assertStatus(422);
    }
}
