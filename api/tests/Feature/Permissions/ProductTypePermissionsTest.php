<?php

namespace Tests\Feature\Permissions;

use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ProductTypePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('product-type-user@test.com');

        $this->category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Drinks',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_product_types(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-types')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_product_types(): void
    {
        $this->grantPermission('product_types', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-types')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_product_type(): void
    {
        $this->grantPermission('product_types', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Soda',
                'product_category_uuid' => $this->category->uuid,
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_product_type(): void
    {
        $this->grantPermission('product_types', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Soda',
                'product_category_uuid' => $this->category->uuid,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_product_type_with_category_from_another_tenant(): void
    {
        $this->grantPermission('product_types', 'create');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Soda',
                'product_category_uuid' => $otherCategory->uuid,
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function user_cannot_create_product_type_with_duplicate_name(): void
    {
        $this->grantPermission('product_types', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Soda',
                'product_category_uuid' => $this->category->uuid,
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Soda',
                'product_category_uuid' => $this->category->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function user_cannot_update_product_type_from_another_tenant(): void
    {
        $this->grantPermission('product_types', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $foreignType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Foreign Type',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/product-types/' . $foreignType->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_product_type_from_another_tenant(): void
    {
        $this->grantPermission('product_types', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $foreignType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Foreign Type',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/product-types/' . $foreignType->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignType);
    }

    #[Test]
    public function listing_can_be_sorted_and_filtered_by_product_category_name(): void
    {
        $this->grantPermission('product_types', 'create');
        $this->grantPermission('product_types', 'read');

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Snacks',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Soda',
                'product_category_uuid' => $this->category->uuid,
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Chips',
                'product_category_uuid' => $otherCategory->uuid,
            ])
            ->assertStatus(201);

        $sorted = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-types?sort_by=product_category_name&sort_dir=desc')
            ->assertStatus(200);

        $sorted->assertJsonPath('data.0.name', 'Chips');
        $sorted->assertJsonPath('data.1.name', 'Soda');

        $filtered = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-types?product_category_name=Snacks')
            ->assertStatus(200);

        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.name', 'Chips');
    }

    #[Test]
    public function listing_can_be_searched_globally_with_q_across_name_and_category(): void
    {
        $this->grantPermission('product_types', 'create');
        $this->grantPermission('product_types', 'read');

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Snacks',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Soda',
                'product_category_uuid' => $this->category->uuid,
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/product-types', [
                'name' => 'Chips',
                'product_category_uuid' => $otherCategory->uuid,
            ])
            ->assertStatus(201);

        // match por name do product_type
        $byName = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-types?q=Soda')
            ->assertStatus(200);
        $byName->assertJsonCount(1, 'data');
        $byName->assertJsonPath('data.0.name', 'Soda');

        // match por product_category_name (relação productCategory)
        $byCategory = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-types?q=Snacks')
            ->assertStatus(200);
        $byCategory->assertJsonCount(1, 'data');
        $byCategory->assertJsonPath('data.0.name', 'Chips');

        $noMatch = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/product-types?q=Nonexistent')
            ->assertStatus(200);
        $noMatch->assertJsonCount(0, 'data');
    }
}
