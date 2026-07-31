<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Storefront\ProductFavorite;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Favoritos de produto pelo cliente final (roadmap Delivery, Fase 4 —
 * retenção). POST /portal/favorites/{product_uuid}/toggle (idempotente) e
 * GET /portal/favorites.
 */
class PortalFavoriteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;
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
        $product = $this->createProduct($tenant->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/favorites/' . $product->uuid . '/toggle');

        $response->assertStatus(200)->assertJsonPath('data.favorited', true);

        $this->assertDatabaseHas('product_favorites', [
            'final_customer_id' => FinalCustomer::where('email', 'cliente@test.com')->value('id'),
            'product_id' => $product->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/favorites/' . $product->uuid . '/toggle');

        $response->assertStatus(200)->assertJsonPath('data.favorited', false);

        $this->assertDatabaseMissing('product_favorites', [
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function favorites_list_is_isolated_per_customer(): void
    {
        [$customerA, $tokenA] = $this->authenticatedCustomer('a@test.com');
        [, $tokenB] = $this->authenticatedCustomer('b@test.com');

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $product = $this->createProduct($tenant->id, ['name' => 'Favorito de A']);

        ProductFavorite::create([
            'final_customer_id' => $customerA->id,
            'product_id' => $product->id,
        ]);

        $responseA = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/portal/favorites');

        $responseA->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $product->uuid)
            ->assertJsonPath('data.0.is_favorited', true);

        $responseB = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/v1/portal/favorites');

        $responseB->assertStatus(200)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function toggle_returns_404_for_nonexistent_product(): void
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
        $product = $this->createProduct($tenant->id);

        $this->postJson('/api/v1/portal/favorites/' . $product->uuid . '/toggle')->assertStatus(401);
        $this->getJson('/api/v1/portal/favorites')->assertStatus(401);
    }
}
