<?php

namespace Tests\Feature\Permissions;

use App\Models\Product\ProductCategory;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ProductCategoryPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('product-category-user@test.com');
    }

    #[Test]
    public function user_without_permission_cannot_list_product_categories(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-categories')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_product_categories(): void
    {
        $this->grantPermission('product_categories', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-categories')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_product_category(): void
    {
        $this->grantPermission('product_categories', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', [
                'name' => 'Beverages',
                'priority' => 1,
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_product_category(): void
    {
        $this->grantPermission('product_categories', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', [
                'name' => 'Beverages',
                'priority' => 1,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_product_category_with_duplicate_name(): void
    {
        $this->grantPermission('product_categories', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', ['name' => 'Beverages'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', ['name' => 'Beverages'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function user_cannot_update_product_category_from_another_tenant(): void
    {
        $this->grantPermission('product_categories', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/product-categories/' . $foreignCategory->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_product_category_from_another_tenant(): void
    {
        $this->grantPermission('product_categories', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/product-categories/' . $foreignCategory->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignCategory);
    }

    #[Test]
    public function listing_can_be_sorted_and_filtered_by_priority_and_name(): void
    {
        $this->grantPermission('product_categories', 'create');
        $this->grantPermission('product_categories', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', ['name' => 'Beverages', 'priority' => 10])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', ['name' => 'Snacks', 'priority' => 1])
            ->assertStatus(201);

        $sorted = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-categories?sort_by=priority&sort_dir=desc')
            ->assertStatus(200);

        $sorted->assertJsonPath('data.0.name', 'Beverages');
        $sorted->assertJsonPath('data.1.name', 'Snacks');

        $filtered = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-categories?priority_min=5&priority_max=20')
            ->assertStatus(200);

        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.name', 'Beverages');

        $byName = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-categories?name=Snack')
            ->assertStatus(200);

        $byName->assertJsonCount(1, 'data');
        $byName->assertJsonPath('data.0.name', 'Snacks');
    }

    #[Test]
    public function listing_can_be_searched_globally_with_q(): void
    {
        $this->grantPermission('product_categories', 'create');
        $this->grantPermission('product_categories', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', ['name' => 'Beverages'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-categories', ['name' => 'Snacks'])
            ->assertStatus(201);

        $matched = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-categories?q=Bev')
            ->assertStatus(200);

        $matched->assertJsonCount(1, 'data');
        $matched->assertJsonPath('data.0.name', 'Beverages');

        $noMatch = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-categories?q=Nonexistent')
            ->assertStatus(200);

        $noMatch->assertJsonCount(0, 'data');
    }
}
