<?php

namespace Tests\Feature\Storefront;

use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Storefront\StoreDeliveryFee;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\GeneratesUniqueUf;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * GET/POST /store-delivery-fees, DELETE /store-delivery-fees/{uuid}
 * (Delivery Fase 2) — CRUD normal, um bairro só pode ter 1 taxa por
 * tenant. Ver App\Services\Storefront\StoreDeliveryFeeService.
 */
class StoreDeliveryFeeTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use GeneratesUniqueUf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('delivery-fee-user@test.com');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createBairro(): Bairro
    {
        $estado = Estado::create(['name' => 'Estado ' . Str::random(6), 'uf' => $this->nextUf()]);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'name' => 'Cidade ' . Str::random(6)]);

        return Bairro::create(['cidade_id' => $cidade->id, 'name' => 'Bairro ' . Str::random(6)]);
    }

    #[Test]
    public function store_creates_a_delivery_fee(): void
    {
        $this->grantPermission('storefront', 'update');
        $bairro = $this->createBairro();

        $response = $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 12.5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.fee', 12.5)
            ->assertJsonPath('data.bairro.uuid', $bairro->uuid);

        $this->assertDatabaseHas('store_delivery_fees', [
            'tenant_id' => $this->tenant->id,
            'bairro_id' => $bairro->id,
            'fee' => 12.5,
        ]);
    }

    #[Test]
    public function store_updates_existing_fee_instead_of_duplicating_when_bairro_repeats(): void
    {
        $this->grantPermission('storefront', 'update');
        $bairro = $this->createBairro();

        $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 10,
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 15.5,
        ])->assertStatus(201)->assertJsonPath('data.fee', 15.5);

        $this->assertDatabaseCount('store_delivery_fees', 1);
        $this->assertDatabaseHas('store_delivery_fees', [
            'tenant_id' => $this->tenant->id,
            'bairro_id' => $bairro->id,
            'fee' => 15.5,
        ]);
    }

    #[Test]
    public function index_lists_only_fees_of_current_tenant(): void
    {
        $this->grantPermission('storefront', 'update');
        $bairro = $this->createBairro();

        $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 8,
        ])->assertStatus(201);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        StoreDeliveryFee::create([
            'tenant_id' => $otherTenant->id,
            'bairro_id' => $bairro->id,
            'fee' => 99,
        ]);

        $response = $this->auth()->getJson('/api/v1/store-delivery-fees')->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(8, $data[0]['fee']);
    }

    #[Test]
    public function destroy_removes_the_fee(): void
    {
        $this->grantPermission('storefront', 'update');
        $bairro = $this->createBairro();

        $created = $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 5,
        ])->assertStatus(201);

        $uuid = $created->json('data.uuid');

        $this->auth()->deleteJson('/api/v1/store-delivery-fees/' . $uuid)->assertStatus(204);

        $this->assertSoftDeleted('store_delivery_fees', [
            'tenant_id' => $this->tenant->id,
            'bairro_id' => $bairro->id,
        ]);
    }

    #[Test]
    public function recreating_fee_after_delete_restores_instead_of_colliding_with_unique_constraint(): void
    {
        $this->grantPermission('storefront', 'update');
        $bairro = $this->createBairro();

        $created = $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 5,
        ])->assertStatus(201);

        $this->auth()->deleteJson('/api/v1/store-delivery-fees/' . $created->json('data.uuid'))
            ->assertStatus(204);

        $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 20.5,
        ])->assertStatus(201)->assertJsonPath('data.fee', 20.5);

        $this->assertDatabaseCount('store_delivery_fees', 1);
    }

    #[Test]
    public function store_requires_existing_bairro(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => (string) Str::uuid(),
            'fee' => 5,
        ])->assertStatus(422);
    }

    #[Test]
    public function user_without_permission_cannot_manage_delivery_fees(): void
    {
        $bairro = $this->createBairro();

        $this->auth()->getJson('/api/v1/store-delivery-fees')->assertStatus(403);
        $this->auth()->postJson('/api/v1/store-delivery-fees', [
            'bairro_uuid' => $bairro->uuid,
            'fee' => 5,
        ])->assertStatus(403);
    }
}
