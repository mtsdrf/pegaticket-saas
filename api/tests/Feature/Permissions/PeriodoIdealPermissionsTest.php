<?php

namespace Tests\Feature\Permissions;

use App\Models\Client\PeriodoIdeal;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class PeriodoIdealPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('periodo-ideal-user@test.com');
    }

    #[Test]
    public function user_without_permission_cannot_list_periodos_ideais(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/periodos-ideais')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_periodos_ideais(): void
    {
        $this->grantPermission('periodos_ideais', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/periodos-ideais')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_periodo_ideal(): void
    {
        $this->grantPermission('periodos_ideais', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/periodos-ideais', ['name' => 'Manhã'])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_periodo_ideal(): void
    {
        $this->grantPermission('periodos_ideais', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/periodos-ideais', ['name' => 'Manhã'])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_periodo_ideal_with_duplicate_name(): void
    {
        $this->grantPermission('periodos_ideais', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/periodos-ideais', ['name' => 'Manhã'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/periodos-ideais', ['name' => 'Manhã'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function user_cannot_update_periodo_ideal_from_another_tenant(): void
    {
        $this->grantPermission('periodos_ideais', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignPeriodoIdeal = PeriodoIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Period',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/periodos-ideais/' . $foreignPeriodoIdeal->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_periodo_ideal_from_another_tenant(): void
    {
        $this->grantPermission('periodos_ideais', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignPeriodoIdeal = PeriodoIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Period',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/periodos-ideais/' . $foreignPeriodoIdeal->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignPeriodoIdeal);
    }
}
