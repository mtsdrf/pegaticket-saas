<?php

namespace Tests\Unit\Repositories;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Repositories\Eloquent\TenantUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected TenantUserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TenantUserRepository::class);
    }

    #[Test]
    public function it_can_create_membership(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Test',
            'slug' => 'tenant-test',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User Test',
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'is_active' => true,
        ]);

        $member = $this->repo->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'tenant_role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(TenantUser::class, $member);
        $this->assertDatabaseHas('tenant_users', [
            'id' => $member->id
        ]);
    }
}