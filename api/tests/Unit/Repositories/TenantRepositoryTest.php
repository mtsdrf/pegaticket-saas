<?php

namespace Tests\Unit\Repositories;

use App\Models\Tenant\Tenant;
use App\Repositories\Eloquent\TenantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Class TenantRepositoryTest
 *
 * Testes unitários para TenantRepository.
 */
class TenantRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected TenantRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TenantRepository(new Tenant());
    }

    #[Test]
    public function it_can_create_tenant(): void
    {
        $data = [
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ];

        $tenant = $this->repository->create($data);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Acme Corp', $tenant->name);
        $this->assertEquals('acme', $tenant->slug);
        $this->assertTrue($tenant->is_active);
    }

    #[Test]
    public function it_can_find_tenant_by_slug(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Company',
            'slug' => 'company',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $found = $this->repository->findBySlug('company');

        $this->assertNotNull($found);
        $this->assertEquals($tenant->id, $found->id);
    }

    #[Test]
    public function it_can_get_id_by_slug(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Corp',
            'slug' => 'corp',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $id = $this->repository->getIdBySlug('corp');

        $this->assertEquals($tenant->id, $id);
    }

    #[Test]
    public function it_can_get_active_tenants(): void
    {
        Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Active',
            'slug' => 'active',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $active = $this->repository->getActiveTenants();

        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active->first()->name);
    }

    #[Test]
    public function it_can_check_if_slug_exists(): void
    {
        Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Existing',
            'slug' => 'existing',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $exists = $this->repository->slugExists('existing');
        $notExists = $this->repository->slugExists('nonexistent');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    #[Test]
    public function it_can_update_tenant(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old',
            'slug' => 'old',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $updated = $this->repository->update($tenant, [
            'name' => 'New',
        ]);

        $this->assertEquals('New', $updated->name);
    }

    #[Test]
    public function it_can_delete_tenant(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Delete Me',
            'slug' => 'delete-me',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $result = $this->repository->delete($tenant);

        $this->assertTrue($result);
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    #[Test]
    public function it_can_paginate_tenants(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => "Tenant {$i}",
                'slug' => "tenant-{$i}",
                'is_active' => true,
                'trial_ends_at' => now()->addDays(30),
            ]);
        }

        $paginated = $this->repository->paginate(2);

        $this->assertEquals(2, $paginated->perPage());
        $this->assertEquals(5, $paginated->total());
    }
}