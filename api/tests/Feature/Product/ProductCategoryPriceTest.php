<?php

namespace Tests\Feature\Product;

use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ProductCategoryPriceTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('product-category-price-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function user_with_update_permission_can_sync_category_prices(): void
    {
        $this->grantPermission('products', 'update');
        $product = $this->createProduct($this->tenant->id);
        $category = $this->createClientCategory($this->tenant->id);

        $response = $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $category->uuid, 'price' => 8.50],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_category_uuid', $category->uuid)
            ->assertJsonPath('data.0.price', '8.50');

        $this->assertDatabaseCount('product_category_prices', 1);
    }

    #[Test]
    public function sync_replaces_full_set_updating_and_removing_entries(): void
    {
        $this->grantPermission('products', 'update');
        $product = $this->createProduct($this->tenant->id);
        $categoryA = $this->createClientCategory($this->tenant->id);
        $categoryB = $this->createClientCategory($this->tenant->id);

        $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $categoryA->uuid, 'price' => 8.50],
                ['client_category_uuid' => $categoryB->uuid, 'price' => 9.00],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseCount('product_category_prices', 2);

        // Segunda chamada: remove categoryB da lista, atualiza preço da A.
        $response = $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $categoryA->uuid, 'price' => 7.00],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_category_uuid', $categoryA->uuid)
            ->assertJsonPath('data.0.price', '7.00');

        $this->assertDatabaseCount('product_category_prices', 1);
    }

    #[Test]
    public function user_with_read_permission_can_list_current_category_prices(): void
    {
        $this->grantPermission('products', 'update');
        $this->grantPermission('products', 'read');
        $product = $this->createProduct($this->tenant->id);
        $category = $this->createClientCategory($this->tenant->id);

        $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $category->uuid, 'price' => 8.50],
            ],
        ])->assertStatus(200);

        $response = $this->auth()->getJson("/api/v1/products/{$product->uuid}/category-prices");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_category_name', $category->name);
    }

    #[Test]
    public function sync_rejects_client_category_uuid_from_another_tenant(): void
    {
        $this->grantPermission('products', 'update');
        $product = $this->createProduct($this->tenant->id);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignCategory = $this->createClientCategory($otherTenant->id);

        $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $foreignCategory->uuid, 'price' => 8.50],
            ],
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['prices.0.client_category_uuid']]);

        $this->assertDatabaseCount('product_category_prices', 0);
    }

    #[Test]
    public function sync_rejects_nonexistent_client_category_uuid(): void
    {
        $this->grantPermission('products', 'update');
        $product = $this->createProduct($this->tenant->id);

        $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => (string) Str::uuid(), 'price' => 8.50],
            ],
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['prices.0.client_category_uuid']]);
    }

    #[Test]
    public function sync_rejects_duplicate_client_category_uuid_in_same_payload(): void
    {
        $this->grantPermission('products', 'update');
        $product = $this->createProduct($this->tenant->id);
        $category = $this->createClientCategory($this->tenant->id);

        $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $category->uuid, 'price' => 8.50],
                ['client_category_uuid' => $category->uuid, 'price' => 9.00],
            ],
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['prices']]);

        $this->assertDatabaseCount('product_category_prices', 0);
    }

    #[Test]
    public function user_cannot_sync_category_prices_of_product_from_another_tenant(): void
    {
        $this->grantPermission('products', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignProduct = $this->createProduct($otherTenant->id);
        $category = $this->createClientCategory($this->tenant->id);

        $this->auth()->postJson("/api/v1/products/{$foreignProduct->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $category->uuid, 'price' => 8.50],
            ],
        ])->assertStatus(404);
    }

    #[Test]
    public function suggested_price_without_client_uuid_returns_product_price(): void
    {
        $this->grantPermission('products', 'read');
        $product = $this->createProduct($this->tenant->id, ['price' => 42.00]);

        $response = $this->auth()->getJson("/api/v1/products/{$product->uuid}/suggested-price");

        $response->assertStatus(200)
            ->assertJsonPath('data.price', '42.00');
    }

    #[Test]
    public function suggested_price_with_client_without_category_override_returns_product_price(): void
    {
        $this->grantPermission('products', 'read');
        $product = $this->createProduct($this->tenant->id, ['price' => 42.00]);
        $client = $this->createClient($this->tenant->id);

        $response = $this->auth()->getJson(
            "/api/v1/products/{$product->uuid}/suggested-price?client_uuid={$client->uuid}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.price', '42.00');
    }

    #[Test]
    public function suggested_price_with_client_category_override_returns_override_price(): void
    {
        $this->grantPermission('products', 'read');
        $this->grantPermission('products', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 42.00]);
        $category = $this->createClientCategory($this->tenant->id);

        $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $category->uuid, 'price' => 30.00],
            ],
        ])->assertStatus(200);

        $client = $this->createClient($this->tenant->id);
        $this->attachClientCategory($client, $category);

        $response = $this->auth()->getJson(
            "/api/v1/products/{$product->uuid}/suggested-price?client_uuid={$client->uuid}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.price', '30.00');
    }

    #[Test]
    public function suggested_price_with_two_categories_uses_lowest_price(): void
    {
        $this->grantPermission('products', 'read');
        $this->grantPermission('products', 'update');
        $product = $this->createProduct($this->tenant->id, ['price' => 42.00]);
        $categoryA = $this->createClientCategory($this->tenant->id);
        $categoryB = $this->createClientCategory($this->tenant->id);

        $this->auth()->postJson("/api/v1/products/{$product->uuid}/category-prices/sync", [
            'prices' => [
                ['client_category_uuid' => $categoryA->uuid, 'price' => 30.00],
                ['client_category_uuid' => $categoryB->uuid, 'price' => 25.00],
            ],
        ])->assertStatus(200);

        $client = $this->createClient($this->tenant->id);
        $this->attachClientCategory($client, $categoryA);
        $this->attachClientCategory($client, $categoryB);

        $response = $this->auth()->getJson(
            "/api/v1/products/{$product->uuid}/suggested-price?client_uuid={$client->uuid}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.price', '25.00');
    }
}
