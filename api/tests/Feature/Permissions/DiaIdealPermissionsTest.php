<?php

namespace Tests\Feature\Permissions;

use App\Models\Client\DiaIdeal;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class DiaIdealPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('dia-ideal-user@test.com');
    }

    #[Test]
    public function user_without_permission_cannot_list_dias_ideais(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/dias-ideais')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_dias_ideais(): void
    {
        $this->grantPermission('dias_ideais', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/dias-ideais')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_dia_ideal(): void
    {
        $this->grantPermission('dias_ideais', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/dias-ideais', ['name' => 'Segunda-feira'])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_dia_ideal(): void
    {
        $this->grantPermission('dias_ideais', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/dias-ideais', ['name' => 'Segunda-feira'])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_dia_ideal_with_duplicate_name(): void
    {
        $this->grantPermission('dias_ideais', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/dias-ideais', ['name' => 'Segunda-feira'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/dias-ideais', ['name' => 'Segunda-feira'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function user_cannot_update_dia_ideal_from_another_tenant(): void
    {
        $this->grantPermission('dias_ideais', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignDiaIdeal = DiaIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Day',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/dias-ideais/' . $foreignDiaIdeal->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_dia_ideal_from_another_tenant(): void
    {
        $this->grantPermission('dias_ideais', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignDiaIdeal = DiaIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Day',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/dias-ideais/' . $foreignDiaIdeal->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignDiaIdeal);
    }
}
