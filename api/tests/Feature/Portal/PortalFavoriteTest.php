<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Storefront\EventFavorite;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Favoritos de EVENTO pelo cliente final (migrado de favorito de
 * produto/ticket type — roadmap PegaTicket seção 4A, 2026-07-31).
 * POST /portal/favorites/{event_uuid}/toggle (idempotente) e
 * GET /portal/favorites.
 */
class PortalFavoriteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;

    private function authenticatedCustomer(string $email): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    #[Test]
    public function toggle_favorites_and_unfavorites_idempotently(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id);
        $event = $ticketType->event;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/favorites/' . $event->uuid . '/toggle');

        $response->assertStatus(200)->assertJsonPath('data.favorited', true);

        $this->assertDatabaseHas('event_favorites', [
            'final_customer_id' => FinalCustomer::where('email', 'cliente@test.com')->value('id'),
            'event_id' => $event->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/favorites/' . $event->uuid . '/toggle');

        $response->assertStatus(200)->assertJsonPath('data.favorited', false);

        $this->assertDatabaseMissing('event_favorites', [
            'event_id' => $event->id,
        ]);
    }

    #[Test]
    public function favorites_list_is_isolated_per_customer(): void
    {
        [$customerA, $tokenA] = $this->authenticatedCustomer('a@test.com');
        [, $tokenB] = $this->authenticatedCustomer('b@test.com');

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['name' => 'Favorito de A']);
        $event = $ticketType->event;

        EventFavorite::create([
            'final_customer_id' => $customerA->id,
            'event_id' => $event->id,
        ]);

        $responseA = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/portal/favorites');

        $responseA->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $event->uuid)
            ->assertJsonPath('data.0.is_favorited', true);

        $responseB = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/v1/portal/favorites');

        $responseB->assertStatus(200)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function toggle_returns_404_for_nonexistent_event(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/favorites/' . Str::uuid() . '/toggle')
            ->assertStatus(404);
    }

    #[Test]
    public function toggle_and_list_require_authentication(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id);
        $event = $ticketType->event;

        $this->postJson('/api/v1/portal/favorites/' . $event->uuid . '/toggle')->assertStatus(401);
        $this->getJson('/api/v1/portal/favorites')->assertStatus(401);
    }
}
