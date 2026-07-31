<?php
// tests/Unit/Repositories/ProductTypeRepositoryTest.php

namespace Tests\Unit\Repositories;

use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Tenant\Tenant;
use App\Repositories\Eloquent\ProductTypeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTypeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ProductTypeRepository $repository;
    protected Tenant $tenant;
    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProductTypeRepository(new ProductType());

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Drinks',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_can_create_type(): void
    {
        $type = $this->repository->create([
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Soda',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(ProductType::class, $type);
        $this->assertEquals('Soda', $type->name);
        $this->assertEquals($this->category->id, $type->product_category_id);
    }

    #[Test]
    public function it_can_get_active_types(): void
    {
        ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Active',
            'is_active' => true,
        ]);

        ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        $types = $this->repository->getActiveTypes($this->tenant->id);

        $this->assertCount(1, $types);
        $this->assertEquals('Active', $types->first()->name);
    }

    #[Test]
    public function it_can_check_name_exists(): void
    {
        ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Can',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->repository->nameExists($this->tenant->id, 'Can')
        );

        $this->assertFalse(
            $this->repository->nameExists($this->tenant->id, 'Bottle')
        );
    }

    #[Test]
    public function it_can_update_type(): void
    {
        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Old',
            'is_active' => true,
        ]);

        $updated = $this->repository->update($type, [
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $updated->name);
    }

    #[Test]
    public function it_can_delete_type(): void
    {
        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Delete',
            'is_active' => true,
        ]);

        $this->repository->delete($type);

        $this->assertSoftDeleted('product_types', ['id' => $type->id]);
    }
}
