<?php

namespace Tests\Unit\Repositories;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Functionality\Functionality;
use App\Models\Permission\Action;
use App\Repositories\Eloquent\TenantRolePermissionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantRolePermissionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_sync_permissions(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant',
            'slug' => 'tenant',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $func = Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Users',
            'slug' => 'users',
            'is_active' => true,
        ]);

        $action = Action::create([
            'uuid' => (string) Str::uuid(),
            'key' => 'read',
            'name' => 'Read',
        ]);

        $repo = new TenantRolePermissionRepository(
            new \App\Models\Tenant\TenantRolePermission()
        );

        $repo->syncPermissions($role->id, [
            [
                'functionality' => 'users',
                'action' => 'read',
            ]
        ]);

        $permissions = $repo->getRolePermissions($role->id);

        $this->assertCount(1, $permissions);
        $this->assertEquals('users', $permissions->first()->functionality);
    }
}