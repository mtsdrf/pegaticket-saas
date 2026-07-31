<?php

namespace Tests\Feature\Permissions;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantRolePermissionIdorTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('role-idor@test.com');
    }

    private function createForeignRole(): TenantRole
    {
        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        return TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Role',
            'slug' => 'foreign-role',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_cannot_list_permissions_of_role_from_another_tenant(): void
    {
        $this->grantPermission('tenant_roles', 'read');
        $foreignRole = $this->createForeignRole();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-roles/' . $foreignRole->uuid . '/permissions')
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_sync_permissions_of_role_from_another_tenant(): void
    {
        $this->grantPermission('tenant_roles', 'update');
        $foreignRole = $this->createForeignRole();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-roles/' . $foreignRole->uuid . '/permissions/sync', [
                'permissions' => [['functionality' => 'clients', 'action' => 'read']],
            ])
            ->assertStatus(404);

        $this->assertDatabaseMissing('tenant_role_permissions', [
            'tenant_role_id' => $foreignRole->id,
        ]);
    }
}
