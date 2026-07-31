<?php

namespace Tests\Feature\Permissions;

use App\Models\Functionality\Functionality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantRoleAvailableFunctionalitiesTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('owner-functionalities@test.com');
    }

    /**
     * Reproduz o bug reportado: dono de empresa (grupo global `clients`,
     * sem a permissão global `functionalities:read`) precisa montar a
     * matriz de permissões ao criar/editar um perfil da própria empresa.
     * O endpoint tenant-scoped não pode depender dessa permissão global.
     */
    #[Test]
    public function tenant_scoped_user_without_global_functionalities_permission_can_list_available_functionalities(): void
    {
        $this->grantPermission('tenant_roles', 'read');

        Functionality::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Clientes',
            'slug' => 'clients',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/functionalities?per_page=100')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-roles/functionalities')
            ->assertStatus(200)
            ->assertJsonFragment(['slug' => 'clients']);
    }

    #[Test]
    public function user_without_tenant_roles_permission_cannot_list_available_functionalities(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-roles/functionalities')
            ->assertStatus(403);
    }
}
