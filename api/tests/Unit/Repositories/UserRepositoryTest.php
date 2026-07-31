<?php

namespace Tests\Unit\Repositories;

use App\Models\Group\Group;
use App\Models\User\User;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Class UserRepositoryTest
 * 
 * Testes unitários para UserRepository.
 * Valida operações de CRUD e queries específicas.
 */
class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository(new User());
    }

    #[Test]
    public function it_can_create_user(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ];

        $user = $this->repository->create($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->uuid);
    }

    #[Test]
    public function it_can_find_user_by_uuid(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $found = $this->repository->findByUuid($user->uuid);

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
        $this->assertEquals($user->email, $found->email);
    }

    #[Test]
    public function it_can_find_user_by_email(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $found = $this->repository->findByEmail('test@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    #[Test]
    public function it_can_update_user(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $updated = $this->repository->update($user, [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new@example.com', $updated->email);
    }

    #[Test]
    public function it_can_delete_user(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'To Delete',
            'email' => 'delete@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $result = $this->repository->delete($user);

        $this->assertTrue($result);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_can_get_active_users(): void
    {
        User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $activeUsers = $this->repository->getActiveUsers();

        $this->assertCount(1, $activeUsers);
        $this->assertEquals('Active User', $activeUsers->first()->name);
    }

    #[Test]
    public function it_can_paginate_with_groups(): void
    {
        // Criar usuários
        for ($i = 1; $i <= 3; $i++) {
            User::create([
                'uuid' => (string) Str::uuid(),
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]);
        }

        $paginated = $this->repository->paginateWithGroups(2);

        $this->assertEquals(2, $paginated->perPage());
        $this->assertEquals(3, $paginated->total());
        $this->assertEquals(2, $paginated->lastPage());
    }

    #[Test]
    public function it_can_sync_groups(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $group1 = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Group 1',
            'slug' => 'group-1',
            'is_active' => true,
        ]);

        $group2 = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Group 2',
            'slug' => 'group-2',
            'is_active' => true,
        ]);

        $result = $this->repository->syncGroups($user, [
            $group1->uuid,
            $group2->uuid,
        ]);

        $this->assertCount(2, $result->groups);
    }

    #[Test]
    public function it_returns_null_when_user_not_found_by_email(): void
    {
        $found = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($found);
    }

    #[Test]
    public function it_can_count_users(): void
    {
        User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $count = $this->repository->count();

        $this->assertEquals(2, $count);
    }
}