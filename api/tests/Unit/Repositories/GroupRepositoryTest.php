<?php

namespace Tests\Unit\Repositories;

use App\Models\Functionality\Functionality;
use App\Models\Group\Group;
use App\Models\Permission\Action;
use App\Models\User\User;
use App\Repositories\Eloquent\GroupRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Class GroupRepositoryTest
 * 
 * Testes unitários para GroupRepository.
 * Valida operações CRUD e sincronização de usuários/permissões.
 */
class GroupRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected GroupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GroupRepository(new Group());
    }

    #[Test]
    public function it_can_create_group(): void
    {
        $data = [
            'name' => 'Administrators',
            'slug' => 'administrators',
            'is_active' => true,
        ];

        $group = $this->repository->create($data);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('Administrators', $group->name);
        $this->assertEquals('administrators', $group->slug);
        $this->assertTrue($group->is_active);
        $this->assertNotNull($group->uuid);
    }

    #[Test]
    public function it_can_find_group_by_uuid(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Editors',
            'slug' => 'editors',
            'is_active' => true,
        ]);

        $found = $this->repository->findByUuid($group->uuid);

        $this->assertNotNull($found);
        $this->assertEquals($group->id, $found->id);
        $this->assertEquals($group->name, $found->name);
    }

    #[Test]
    public function it_can_find_group_by_slug(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Managers',
            'slug' => 'managers',
            'is_active' => true,
        ]);

        $found = $this->repository->findBySlug('managers');

        $this->assertNotNull($found);
        $this->assertEquals($group->id, $found->id);
    }

    #[Test]
    public function it_can_update_group(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old Name',
            'slug' => 'old-slug',
            'is_active' => true,
        ]);

        $updated = $this->repository->update($group, [
            'name' => 'New Name',
            'slug' => 'new-slug',
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new-slug', $updated->slug);
    }

    #[Test]
    public function it_can_delete_group(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'is_active' => true,
        ]);

        $result = $this->repository->delete($group);

        $this->assertTrue($result);
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
    }

    #[Test]
    public function it_can_get_active_groups(): void
    {
        Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Active Group',
            'slug' => 'active-group',
            'is_active' => true,
        ]);

        Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive Group',
            'slug' => 'inactive-group',
            'is_active' => false,
        ]);

        $activeGroups = $this->repository->getActiveGroups();

        $this->assertCount(1, $activeGroups);
        $this->assertEquals('Active Group', $activeGroups->first()->name);
    }

    #[Test]
    public function it_can_get_ids_by_uuids(): void
    {
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

        $ids = $this->repository->getIdsByUuids([
            $group1->uuid,
            $group2->uuid,
        ]);

        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($group1->id));
        $this->assertTrue($ids->contains($group2->id));
    }

    #[Test]
    public function it_can_sync_users(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        $user1 = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 1',
            'email' => 'user1@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $user2 = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 2',
            'email' => 'user2@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->repository->syncUsers($group, [
            $user1->uuid,
            $user2->uuid,
        ]);

        // Verificar que os usuários foram vinculados
        $userCount = DB::table('group_user')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->count();

        $this->assertEquals(2, $userCount);
    }

    #[Test]
    public function it_can_sync_users_with_soft_delete_of_previous(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        $user1 = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 1',
            'email' => 'user1@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $user2 = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 2',
            'email' => 'user2@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Primeira sincronização
        $this->repository->syncUsers($group, [$user1->uuid]);

        // Segunda sincronização (deve soft delete user1 e adicionar user2)
        $this->repository->syncUsers($group, [$user2->uuid]);

        // Verificar apenas user2 está ativo
        $activeUsers = DB::table('group_user')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->count();

        $this->assertEquals(1, $activeUsers);

        // Verificar que user1 foi soft deleted
        $deletedUsers = DB::table('group_user')
            ->where('group_id', $group->id)
            ->whereNotNull('deleted_at')
            ->count();

        $this->assertEquals(1, $deletedUsers);
    }

    #[Test]
    public function it_can_sync_permissions(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        $functionality = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Users',
            'slug' => 'users',
            'is_active' => true,
        ]);

        $readAction = Action::create([
            'key' => 'read',
            'name' => 'Read',
        ]);

        $createAction = Action::create([
            'key' => 'create',
            'name' => 'Create',
        ]);

        $permissions = [
            [
                'functionality_slug' => 'users',
                'actions' => ['read', 'create'],
            ],
        ];

        $this->repository->syncPermissions($group, $permissions);

        // Verificar que as permissões foram criadas
        $permissionCount = DB::table('group_permissions')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->count();

        $this->assertEquals(2, $permissionCount);
    }

    #[Test]
    public function it_can_get_group_users(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        $user1 = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 1',
            'email' => 'user1@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $user2 = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User 2',
            'email' => 'user2@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Adicionar usuários ao grupo
        DB::table('group_user')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'group_id' => $group->id,
                'user_id' => $user1->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'group_id' => $group->id,
                'user_id' => $user2->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $users = $this->repository->getGroupUsers($group);

        $this->assertCount(2, $users);
    }

    #[Test]
    public function it_can_get_group_permissions(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        $functionality = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Users',
            'slug' => 'users',
            'is_active' => true,
        ]);

        $action = Action::create([
            'key' => 'read',
            'name' => 'Read',
        ]);

        // Adicionar permissão
        DB::table('group_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $group->id,
            'functionality_id' => $functionality->id,
            'action_id' => $action->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = $this->repository->getGroupPermissions($group);

        $this->assertCount(1, $permissions);
        $this->assertEquals('users', $permissions->first()->functionality_slug);
        $this->assertEquals('read', $permissions->first()->action_key);
    }

    #[Test]
    public function it_can_paginate_groups(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Group::create([
                'uuid' => (string) Str::uuid(),
                'name' => "Group {$i}",
                'slug' => "group-{$i}",
                'is_active' => true,
            ]);
        }

        $paginated = $this->repository->paginate(2);

        $this->assertEquals(2, $paginated->perPage());
        $this->assertEquals(5, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());
    }

    #[Test]
    public function it_can_count_groups(): void
    {
        Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Group 1',
            'slug' => 'group-1',
            'is_active' => true,
        ]);

        Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Group 2',
            'slug' => 'group-2',
            'is_active' => true,
        ]);

        $count = $this->repository->count();

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function it_returns_null_when_group_not_found_by_slug(): void
    {
        $found = $this->repository->findBySlug('nonexistent');

        $this->assertNull($found);
    }

    #[Test]
    public function it_ignores_soft_deleted_groups_in_queries(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Deleted Group',
            'slug' => 'deleted-group',
            'is_active' => true,
        ]);

        // Soft delete
        $group->delete();

        // Buscar por UUID não deve encontrar
        $found = $this->repository->findByUuid($group->uuid);
        $this->assertNull($found);

        // Buscar por slug não deve encontrar
        $foundBySlug = $this->repository->findBySlug('deleted-group');
        $this->assertNull($foundBySlug);

        // Count não deve incluir
        $count = $this->repository->count();
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function sync_permissions_skips_invalid_functionalities(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        Action::create(['key' => 'read', 'name' => 'Read']);

        $permissions = [
            [
                'functionality_slug' => 'nonexistent-functionality',
                'actions' => ['read'],
            ],
        ];

        // Não deve lançar erro, apenas pular
        $this->repository->syncPermissions($group, $permissions);

        $permissionCount = DB::table('group_permissions')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->count();

        $this->assertEquals(0, $permissionCount);
    }

    #[Test]
    public function sync_permissions_skips_invalid_actions(): void
    {
        $group = Group::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Users',
            'slug' => 'users',
            'is_active' => true,
        ]);

        $permissions = [
            [
                'functionality_slug' => 'users',
                'actions' => ['nonexistent-action'],
            ],
        ];

        // Não deve lançar erro, apenas pular
        $this->repository->syncPermissions($group, $permissions);

        $permissionCount = DB::table('group_permissions')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->count();

        $this->assertEquals(0, $permissionCount);
    }
}