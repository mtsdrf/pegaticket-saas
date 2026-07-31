<?php

namespace Tests\Feature\Permissions;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User\User;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use PHPUnit\Framework\Attributes\Test;

class TenantRolePermissionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected TenantRole $role;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Perm User',
            'email' => 'perm@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'perm@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->token = $login['access_token'];

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Test',
            'slug' => 'tenant-test',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_sync_role_permissions(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/tenant-roles/{$this->role->uuid}/permissions/sync", [
                'permissions' => []
            ])
            ->assertStatus(403);
    }
}