<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\ProductPromotion;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * GET/POST/DELETE /product-promotions (Delivery Fase 3) — upsert 1 por
 * produto, mesmo shape de StoreDeliveryFeeTest.
 */
class ProductPromotionTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('promotion-user@test.com');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function store_creates_a_promotion(): void
    {
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $response = $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 15.5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.promo_price', 15.5)
            ->assertJsonPath('data.ticketType.uuid', $product->uuid);

        $this->assertDatabaseHas('product_promotions', [
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $product->id,
            'promo_price' => 15.5,
        ]);
    }

    #[Test]
    public function store_updates_existing_promotion_instead_of_duplicating_when_product_repeats(): void
    {
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 15.5,
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 12.5,
        ])->assertStatus(201)->assertJsonPath('data.promo_price', 12.5);

        $this->assertDatabaseCount('product_promotions', 1);
    }

    #[Test]
    public function index_lists_only_promotions_of_current_tenant(): void
    {
        $this->grantPermission('storefront', 'read');
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 15,
        ])->assertStatus(201);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
        $otherProduct = $this->createProduct($otherTenant->id, ['price' => 20]);

        ProductPromotion::create([
            'tenant_id' => $otherTenant->id,
            'ticket_type_id' => $otherProduct->id,
            'promo_price' => 5,
        ]);

        $response = $this->auth()->getJson('/api/v1/product-promotions')->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals(15, $data[0]['promo_price']);
    }

    #[Test]
    public function destroy_removes_the_promotion(): void
    {
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $created = $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 15,
        ])->assertStatus(201);

        $uuid = $created->json('data.uuid');

        $this->auth()->deleteJson('/api/v1/product-promotions/' . $uuid)->assertStatus(204);

        $this->assertSoftDeleted('product_promotions', [
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $product->id,
        ]);
    }

    #[Test]
    public function recreating_promotion_after_delete_restores_instead_of_colliding_with_unique_constraint(): void
    {
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $created = $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 15.5,
        ])->assertStatus(201);

        $this->auth()->deleteJson('/api/v1/product-promotions/' . $created->json('data.uuid'))
            ->assertStatus(204);

        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 8.5,
        ])->assertStatus(201)->assertJsonPath('data.promo_price', 8.5);

        $this->assertDatabaseCount('product_promotions', 1);
    }

    #[Test]
    public function store_requires_existing_product(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => (string) Str::uuid(),
            'promo_price' => 10,
        ])->assertStatus(422);
    }

    #[Test]
    public function store_creates_a_percentage_promotion(): void
    {
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $response = $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'discount_type' => 'percentage',
            'discount_percentage' => 25,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.discount_type', 'percentage')
            ->assertJsonPath('data.discount_percentage', 25)
            ->assertJsonPath('data.promo_price', null)
            ->assertJsonPath('data.effective_price', 15);

        $this->assertDatabaseHas('product_promotions', [
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $product->id,
            'discount_type' => 'percentage',
            'discount_percentage' => 25,
        ]);
    }

    #[Test]
    public function store_requires_promo_price_for_fixed_price_type(): void
    {
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'discount_type' => 'fixed_price',
        ])->assertStatus(422)->assertJsonValidationErrors(['promo_price']);
    }

    #[Test]
    public function store_requires_discount_percentage_for_percentage_type(): void
    {
        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'discount_type' => 'percentage',
        ])->assertStatus(422)->assertJsonValidationErrors(['discount_percentage']);
    }

    #[Test]
    public function user_without_permission_cannot_manage_promotions(): void
    {
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);

        $this->auth()->getJson('/api/v1/product-promotions')->assertStatus(403);
        $this->auth()->postJson('/api/v1/product-promotions', [
            'ticket_type_uuid' => $product->uuid,
            'promo_price' => 10,
        ])->assertStatus(403);
    }
}
