<?php

namespace Tests\Feature\Tenant;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantUserCreateTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('tenant-user-admin@test.com');
        $this->grantPermission('tenant_users', 'create');
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    #[Test]
    public function it_creates_a_new_user_and_links_it_to_the_current_tenant(): void
    {
        $roleUuid = $this->tenantRole->uuid;

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/tenant-users', [
                'tenant_uuid' => $this->tenant->uuid,
                'role_uuid' => $roleUuid,
                'is_active' => true,
                'user' => [
                    'name' => 'Novo Colaborador',
                    'email' => 'novo.colaborador@test.com',
                    'password' => 'Password@123',
                ],
            ])
            ->assertStatus(201);

        $tenantUserUuid = $response->json('data.uuid');
        $userUuid = $response->json('data.user_uuid');

        $this->assertNotNull($tenantUserUuid);
        $this->assertNotNull($userUuid);
        $this->assertSame('novo.colaborador@test.com', $response->json('data.user_email'));

        $user = User::where('uuid', $userUuid)->firstOrFail();

        $this->assertTrue(Hash::check('Password@123', $user->password));
        $this->assertDatabaseHas('tenant_users', [
            'uuid' => $tenantUserUuid,
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }
}
