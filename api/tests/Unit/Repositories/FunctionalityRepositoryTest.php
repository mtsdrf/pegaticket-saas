<?php

namespace Tests\Unit\Repositories;

use App\Models\Functionality\Functionality;
use App\Repositories\Eloquent\FunctionalityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Class FunctionalityRepositoryTest
 * 
 * Testes unitários para FunctionalityRepository.
 */
class FunctionalityRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected FunctionalityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new FunctionalityRepository(new Functionality());
    }

    #[Test]
    public function it_can_create_functionality(): void
    {
        $data = [
            'name' => 'Users Management',
            'slug' => 'users',
            'description' => 'Manage system users',
            'is_active' => true,
        ];

        $functionality = $this->repository->create($data);

        $this->assertInstanceOf(Functionality::class, $functionality);
        $this->assertEquals('Users Management', $functionality->name);
        $this->assertEquals('users', $functionality->slug);
        $this->assertTrue($functionality->is_active);
    }

    #[Test]
    public function it_can_find_functionality_by_slug(): void
    {
        $functionality = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Reports',
            'slug' => 'reports',
            'is_active' => true,
        ]);

        $found = $this->repository->findBySlug('reports');

        $this->assertNotNull($found);
        $this->assertEquals($functionality->id, $found->id);
    }

    #[Test]
    public function it_can_get_id_by_slug(): void
    {
        $functionality = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Settings',
            'slug' => 'settings',
            'is_active' => true,
        ]);

        $id = $this->repository->getIdBySlug('settings');

        $this->assertEquals($functionality->id, $id);
    }

    #[Test]
    public function it_can_get_active_functionalities(): void
    {
        Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Active Func',
            'slug' => 'active-func',
            'is_active' => true,
        ]);

        Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive Func',
            'slug' => 'inactive-func',
            'is_active' => false,
        ]);

        $active = $this->repository->getActiveFunctionalities();

        $this->assertCount(1, $active);
        $this->assertEquals('Active Func', $active->first()->name);
    }

    #[Test]
    public function it_can_check_if_slug_exists(): void
    {
        Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Existing',
            'slug' => 'existing-slug',
            'is_active' => true,
        ]);

        $exists = $this->repository->slugExists('existing-slug');
        $notExists = $this->repository->slugExists('non-existing-slug');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    #[Test]
    public function it_can_check_slug_exists_excluding_id(): void
    {
        $func = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Original',
            'slug' => 'original-slug',
            'is_active' => true,
        ]);

        // Não deve considerar o próprio registro
        $exists = $this->repository->slugExists('original-slug', $func->id);

        $this->assertFalse($exists);
    }

    #[Test]
    public function it_can_update_functionality(): void
    {
        $functionality = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old Name',
            'slug' => 'old-slug',
            'is_active' => true,
        ]);

        $updated = $this->repository->update($functionality, [
            'name' => 'New Name',
            'slug' => 'new-slug',
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new-slug', $updated->slug);
    }

    #[Test]
    public function it_can_delete_functionality(): void
    {
        $functionality = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'is_active' => true,
        ]);

        $result = $this->repository->delete($functionality);

        $this->assertTrue($result);
        $this->assertSoftDeleted('functionalities', ['id' => $functionality->id]);
    }

    #[Test]
    public function it_can_paginate_functionalities(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Functionality::create([
                'uuid' => (string) Str::uuid(),
                'name' => "Func {$i}",
                'slug' => "func-{$i}",
                'is_active' => true,
            ]);
        }

        $paginated = $this->repository->paginate(2);

        $this->assertEquals(2, $paginated->perPage());
        $this->assertEquals(5, $paginated->total());
    }

    #[Test]
    public function it_returns_null_when_slug_not_found(): void
    {
        $found = $this->repository->findBySlug('nonexistent');

        $this->assertNull($found);
    }
}