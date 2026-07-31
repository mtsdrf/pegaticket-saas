<?php

namespace Tests\Feature\Permissions;

use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class StockLocationPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('stock-location-user@test.com');
    }

    #[Test]
    public function user_without_permission_cannot_list_stock_locations(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/stock-locations')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_stock_locations(): void
    {
        $this->grantPermission('stock_locations', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/stock-locations')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_stock_location(): void
    {
        $this->grantPermission('stock_locations', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/stock-locations', ['name' => 'Depósito 2'])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_stock_location(): void
    {
        $this->grantPermission('stock_locations', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/stock-locations', [
                'name' => 'Depósito 2',
                'type' => 'deposito',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Depósito 2');
    }

    #[Test]
    public function user_cannot_create_stock_location_with_duplicate_name(): void
    {
        $this->grantPermission('stock_locations', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/stock-locations', ['name' => 'Depósito 2'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/stock-locations', ['name' => 'Depósito 2'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function is_default_is_exclusive_per_tenant(): void
    {
        $this->grantPermission('stock_locations', 'create');

        $locationA = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/stock-locations', [
                'name' => 'Depósito A',
                'is_default' => true,
            ])
            ->assertStatus(201)
            ->json('data');

        $this->assertTrue(StockLocation::where('uuid', $locationA['uuid'])->first()->is_default);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/stock-locations', [
                'name' => 'Depósito B',
                'is_default' => true,
            ])
            ->assertStatus(201);

        $this->assertFalse(StockLocation::where('uuid', $locationA['uuid'])->first()->is_default);
    }

    #[Test]
    public function user_cannot_update_stock_location_from_another_tenant(): void
    {
        $this->grantPermission('stock_locations', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignLocation = StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Location',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/stock-locations/' . $foreignLocation->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_stock_location_from_another_tenant(): void
    {
        $this->grantPermission('stock_locations', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignLocation = StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Location',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/stock-locations/' . $foreignLocation->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignLocation);
    }
}
