<?php
// tests/Unit/Repositories/ClientCategoryRepositoryTest.php

namespace Tests\Unit\Repositories;

use App\Models\Client\ClientCategory;
use App\Models\Tenant\Tenant;
use App\Repositories\Eloquent\ClientCategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ClientCategoryRepository $repository;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ClientCategoryRepository(new ClientCategory());

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
            'name' => 'VIP',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(ClientCategory::class, $category);
        $this->assertEquals('VIP', $category->name);
        $this->assertEquals($this->tenant->id, $category->tenant_id);
    }

    #[Test]
    public function it_can_get_active_categories(): void
    {
        ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Active',
            'is_active' => true,
        ]);

        ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        $categories = $this->repository->getActiveCategories($this->tenant->id);

        $this->assertCount(1, $categories);
        $this->assertEquals('Active', $categories->first()->name);
    }

    #[Test]
    public function it_can_check_name_exists(): void
    {
        ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Finance',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->repository->nameExists($this->tenant->id, 'Finance')
        );

        $this->assertFalse(
            $this->repository->nameExists($this->tenant->id, 'Marketing')
        );
    }

    #[Test]
    public function it_can_update_category(): void
    {
        $category = ClientCategory::create([
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
        $category = ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Delete',
            'is_active' => true,
        ]);

        $this->repository->delete($category);

        $this->assertSoftDeleted('client_categories', ['id' => $category->id]);
    }
}
