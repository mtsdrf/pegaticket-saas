<?php
// tests/Unit/Repositories/ProductCategoryRepositoryTest.php

namespace Tests\Unit\Repositories;

use App\Models\Product\ProductCategory;
use App\Models\Tenant\Tenant;
use App\Repositories\Eloquent\ProductCategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ProductCategoryRepository $repository;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProductCategoryRepository(new ProductCategory());

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    #[Test]
    public function it_can_create_category(): void
    {
        $category = $this->repository->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Beverages',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(ProductCategory::class, $category);
        $this->assertEquals('Beverages', $category->name);
        $this->assertEquals(1, $category->priority);
        $this->assertEquals($this->tenant->id, $category->tenant_id);
    }

    #[Test]
    public function it_can_get_active_categories_ordered_by_priority(): void
    {
        ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Second',
            'priority' => 2,
            'is_active' => true,
        ]);

        ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'First',
            'priority' => 1,
            'is_active' => true,
        ]);

        ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive',
            'priority' => 0,
            'is_active' => false,
        ]);

        $categories = $this->repository->getActiveCategories($this->tenant->id);

        $this->assertCount(2, $categories);
        $this->assertEquals('First', $categories->first()->name);
    }

    #[Test]
    public function it_can_check_name_exists(): void
    {
        ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Snacks',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->repository->nameExists($this->tenant->id, 'Snacks')
        );

        $this->assertFalse(
            $this->repository->nameExists($this->tenant->id, 'Frozen')
        );
    }

    #[Test]
    public function it_can_update_category(): void
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Old',
            'is_active' => true,
        ]);

        $updated = $this->repository->update($category, [
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $updated->name);
    }

    #[Test]
    public function it_can_delete_category(): void
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Delete',
            'is_active' => true,
        ]);

        $this->repository->delete($category);

        $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
    }
}
