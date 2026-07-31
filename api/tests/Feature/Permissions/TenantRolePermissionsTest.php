<?php

namespace Tests\Feature\Permissions;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantRolePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('role@test.com');
    }

    #[Test]
    public function user_without_permission_cannot_list_roles(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-roles')
            ->assertStatus(403);
    }

    #[Test]
    public function user_cannot_create_tenant_role_with_duplicate_slug(): void
    {
        $this->grantPermission('tenant_roles', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-roles', ['name' => 'Manager', 'slug' => 'manager'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-roles', ['name' => 'Manager 2', 'slug' => 'manager'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_SLUG');
    }

    #[Test]
    public function user_cannot_update_tenant_role_from_another_tenant(): void
    {
        $this->grantPermission('tenant_roles', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignRole = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Role',
            'slug' => 'foreign-role',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-roles/' . $foreignRole->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_tenant_role_from_another_tenant(): void
    {
        $this->grantPermission('tenant_roles', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignRole = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Role',
            'slug' => 'foreign-role',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/tenant-roles/' . $foreignRole->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignRole);
    }
}
