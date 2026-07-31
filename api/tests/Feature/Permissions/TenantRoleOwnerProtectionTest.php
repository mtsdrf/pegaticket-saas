<?php

namespace Tests\Feature\Permissions;

use App\Models\Tenant\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Cobre a proteção do perfil "owner": dono da empresa (grupo global
 * 'clients') não pode alterar/sincronizar permissões/excluir o role owner
 * da própria tenant, mesmo tendo tenant_roles:update/delete. Administrador
 * da plataforma (grupo 'administrators') pode editar/sincronizar o owner,
 * mas não excluí-lo (bloqueio de delete é incondicional).
 */
class TenantRoleOwnerProtectionTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected TenantRole $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('owner-protection@test.com');

        $this->ownerRole = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Proprietário',
            'slug' => 'owner',
            'is_active' => true,
        ]);

        // Mirrors SelfSignupService::register: dono real de empresa cai no
        // grupo global 'clients'.
        $clientsGroupId = DB::table('groups')->where('slug', 'clients')->value('id');

        if (!$clientsGroupId) {
            $clientsGroupId = DB::table('groups')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Clients',
                'slug' => 'clients',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $clientsGroupId,
            'user_id' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function client_owner_cannot_update_the_owner_role(): void
    {
        $this->grantPermission('tenant_roles', 'update');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-roles/' . $this->ownerRole->uuid, ['name' => 'Hijacked Owner'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'PROTECTED_ROLE');

        $this->assertSame('Proprietário', $this->ownerRole->fresh()->name);
    }

    #[Test]
    public function client_owner_cannot_sync_permissions_of_the_owner_role(): void
    {
        $this->grantPermission('tenant_roles', 'update');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/tenant-roles/{$this->ownerRole->uuid}/permissions/sync", [
                'permissions' => [
                    ['functionality' => 'tenant_roles', 'action' => 'read'],
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'PROTECTED_ROLE');
    }

    #[Test]
    public function client_owner_cannot_delete_the_owner_role(): void
    {
        $this->grantPermission('tenant_roles', 'delete');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/tenant-roles/' . $this->ownerRole->uuid)
            ->assertStatus(403)
            ->assertJsonPath('code', 'PROTECTED_ROLE');

        $this->assertNotSoftDeleted($this->ownerRole);
    }

    #[Test]
    public function client_owner_can_create_a_new_role_and_manage_its_permissions(): void
    {
        $this->grantPermission('tenant_roles', 'create');
        $this->grantPermission('tenant_roles', 'update');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-roles', ['name' => 'Vendedor', 'slug' => 'vendedor'])
            ->assertStatus(201)
            ->json('data');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-roles/' . $created['uuid'], ['name' => 'Vendedor Sênior'])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/tenant-roles/{$created['uuid']}/permissions/sync", [
                'permissions' => [
                    ['functionality' => 'tenant_roles', 'action' => 'read'],
                ],
            ])
            ->assertStatus(200);
    }

    #[Test]
    public function platform_administrator_can_update_and_sync_the_owner_role_but_not_delete_it(): void
    {
        $adminGroupId = DB::table('groups')->where('slug', 'administrators')->value('id');

        if (!$adminGroupId) {
            $adminGroupId = DB::table('groups')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Administrators',
                'slug' => 'administrators',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $adminGroupId,
            'user_id' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->grantPermission('tenant_roles', 'update');
        $this->grantPermission('tenant_roles', 'delete');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-roles/' . $this->ownerRole->uuid, ['name' => 'Proprietário Renomeado'])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/tenant-roles/{$this->ownerRole->uuid}/permissions/sync", [
                'permissions' => [
                    ['functionality' => 'tenant_roles', 'action' => 'read'],
                ],
            ])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/tenant-roles/' . $this->ownerRole->uuid)
            ->assertStatus(403)
            ->assertJsonPath('code', 'PROTECTED_ROLE');

        $this->assertNotSoftDeleted($this->ownerRole);
    }
}
