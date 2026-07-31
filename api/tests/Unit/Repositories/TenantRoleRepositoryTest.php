<?php
// tests/Unit/Repositories/TenantRoleRepositoryTest.php

namespace Tests\Unit\Repositories;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Repositories\Eloquent\TenantRoleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantRoleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected TenantRoleRepository $repository;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new TenantRoleRepository(new TenantRole());

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    #[Test]
    public function it_can_create_role(): void
    {
        $role = $this->repository->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(TenantRole::class, $role);
        $this->assertEquals('Manager', $role->name);
        $this->assertEquals($this->tenant->id, $role->tenant_id);
    }

    #[Test]
    public function it_can_find_role_by_slug(): void
    {
        TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'is_active' => true,
        ]);

        $found = $this->repository->findBySlug($this->tenant->id, 'admin');

        $this->assertNotNull($found);
        $this->assertEquals('Admin', $found->name);
    }

    #[Test]
    public function it_can_get_active_roles(): void
    {
        TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Role',
            'slug' => 'active',
            'is_active' => true,
        ]);

        TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Role',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $roles = $this->repository->getActiveRoles($this->tenant->id);

        $this->assertCount(1, $roles);
        $this->assertEquals('Active Role', $roles->first()->name);
    }

    #[Test]
    public function it_can_check_slug_exists(): void
    {
        TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Finance',
            'slug' => 'finance',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->repository->slugExists($this->tenant->id, 'finance')
        );

        $this->assertFalse(
            $this->repository->slugExists($this->tenant->id, 'marketing')
        );
    }

    #[Test]
    public function it_can_update_role(): void
    {
        $role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Old',
            'slug' => 'old',
            'is_active' => true,
        ]);

        $updated = $this->repository->update($role, [
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $updated->name);
    }

    #[Test]
    public function it_can_delete_role(): void
    {
        $role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Delete',
            'slug' => 'delete',
            'is_active' => true,
        ]);

        $this->repository->delete($role);

        $this->assertSoftDeleted('tenant_roles', ['id' => $role->id]);
    }
}