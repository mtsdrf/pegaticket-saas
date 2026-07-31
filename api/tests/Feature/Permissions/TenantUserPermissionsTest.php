<?php

namespace Tests\Feature\Permissions;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantUserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'TenantUser',
            'email' => 'tenantuser@test.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'tenantuser@test.com',
            'password' => 'password123'
        ])->json('data');

        $this->token = $login['access_token'];
    }

    public function test_user_without_permission_cannot_list_members(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-users')
            ->assertStatus(403);
    }
}