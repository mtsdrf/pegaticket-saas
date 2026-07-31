<?php

namespace Tests\Feature\Stock;

use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockLocation;
use App\Models\Stock\StockMovement;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('stock-movement-user@test.com');
    }

    protected function createProduct(int $tenantId, array $overrides = []): Product
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Category ' . Str::random(6),
            'is_active' => true,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_category_id' => $category->id,
            'name' => 'Type ' . Str::random(6),
            'is_active' => true,
        ]);

        return Product::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_type_id' => $type->id,
            'name' => 'Product ' . Str::random(6),
            'price' => 10,
            'is_available' => true,
            'stock_quantity' => 0,
        ], $overrides));
    }

    protected function createLocation(int $tenantId, array $overrides = []): StockLocation
    {
        return StockLocation::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Location ' . Str::random(6),
            'is_active' => true,
        ], $overrides));
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function entry_increases_on_hand_balance(): void
    {
        $this->grantPermission('stock', 'entry');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 10,
            'reason' => 'Compra inicial',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'entry')
            ->assertJsonPath('data.balance_before', '0.000')
            ->assertJsonPath('data.balance_after', '10.000');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertNotNull($balance);
        $this->assertEquals(10, $balance->quantity_on_hand);
        $this->assertEquals(10, $balance->quantity_available);
    }

    /**
     * CMV real (roadmap A3.13) — unit_cost é opcional na entrada e fica
     * gravado no movimento (usado depois pelo custo médio ponderado em
     * GET /reports/cmv).
     */
    #[Test]
    public function entry_accepts_optional_unit_cost(): void
    {
        $this->grantPermission('stock', 'entry');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 10,
            'reason' => 'Compra com custo',
            'unit_cost' => 5.50,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.unit_cost', 5.5);

        $movement = StockMovement::where('product_id', $product->id)->where('type', 'entry')->first();
        $this->assertEquals(5.5, (float) $movement->unit_cost);
    }

    #[Test]
    public function entry_without_unit_cost_stores_null(): void
    {
        $this->grantPermission('stock', 'entry');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 10,
            'reason' => 'Compra sem custo informado',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.unit_cost', null);

        $movement = StockMovement::where('product_id', $product->id)->where('type', 'entry')->first();
        $this->assertNull($movement->unit_cost);
    }

    /**
     * Fase 8 (migração de dados reais) encontrou produto vendido por peso
     * (ex.: queijo/doces por kg) com quantidade fracionária real nos dados
     * — quantity_on_hand/reserved/blocked viraram decimal(12,3) por causa
     * disso. Este teste cobre o caso base: entrada e saída de 0.5 unidade.
     */
    #[Test]
    public function entry_and_exit_support_fractional_quantity(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'exit');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 1.5,
            'reason' => 'Compra fracionada (peso)',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'entry')
            ->assertJsonPath('data.quantity', '1.500')
            ->assertJsonPath('data.balance_before', '0.000')
            ->assertJsonPath('data.balance_after', '1.500');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEqualsWithDelta(1.5, (float) $balance->quantity_on_hand, 0.0001);
        $this->assertEqualsWithDelta(1.5, $balance->quantity_available, 0.0001);

        $this->auth()->postJson('/api/v1/stock/movements/exit', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 0.5,
            'reason' => 'Venda fracionada (peso)',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'exit')
            ->assertJsonPath('data.balance_after', '1.000');

        $balance->refresh();
        $this->assertEqualsWithDelta(1.0, (float) $balance->quantity_on_hand, 0.0001);
        $this->assertEqualsWithDelta(1.0, $balance->quantity_available, 0.0001);
    }

    #[Test]
    public function exit_decreases_balance_and_blocks_when_insufficient(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'exit');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 5,
            'reason' => 'Entrada',
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/stock/movements/exit', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 3,
            'reason' => 'Venda',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'exit')
            ->assertJsonPath('data.balance_after', '2.000');

        $this->auth()->postJson('/api/v1/stock/movements/exit', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 100,
            'reason' => 'Venda grande demais',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INSUFFICIENT_STOCK');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(2, $balance->quantity_on_hand);
    }

    #[Test]
    public function adjustment_supports_both_directions(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'adjustment');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 10,
            'reason' => 'Entrada',
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/stock/movements/adjustment', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 5,
            'direction' => 'increase',
            'reason' => 'Contagem encontrou a mais',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'adjustment_positive')
            ->assertJsonPath('data.balance_after', '15.000');

        $this->auth()->postJson('/api/v1/stock/movements/adjustment', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 4,
            'direction' => 'decrease',
            'reason' => 'Contagem encontrou a menos',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'adjustment_negative')
            ->assertJsonPath('data.balance_after', '11.000');
    }

    #[Test]
    public function transfer_moves_balance_atomically_between_locations(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'transfer');
        $product = $this->createProduct($this->tenant->id);
        $locationA = $this->createLocation($this->tenant->id, ['name' => 'A']);
        $locationB = $this->createLocation($this->tenant->id, ['name' => 'B']);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $locationA->uuid,
            'quantity' => 20,
            'reason' => 'Entrada',
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/stock/movements/transfer', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $locationA->uuid,
            'destination_location_uuid' => $locationB->uuid,
            'quantity' => 8,
            'reason' => 'Redistribuição',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.balance_after', '12.000');

        $balanceA = StockBalance::where('product_id', $product->id)->where('location_id', $locationA->id)->first();
        $balanceB = StockBalance::where('product_id', $product->id)->where('location_id', $locationB->id)->first();

        $this->assertEquals(12, $balanceA->quantity_on_hand);
        $this->assertEquals(8, $balanceB->quantity_on_hand);
    }

    #[Test]
    public function transfer_rejects_same_origin_and_destination(): void
    {
        $this->grantPermission('stock', 'transfer');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/transfer', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'destination_location_uuid' => $location->uuid,
            'quantity' => 1,
            'reason' => 'Teste',
        ])->assertStatus(422);
    }

    #[Test]
    public function block_and_unblock_only_change_blocked_quantity(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'block');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 10,
            'reason' => 'Entrada',
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/stock/movements/block', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 4,
            'reason' => 'Avaria em análise',
        ])->assertStatus(201)->assertJsonPath('data.type', 'block');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(10, $balance->quantity_on_hand);
        $this->assertEquals(4, $balance->quantity_blocked);
        $this->assertEquals(6, $balance->quantity_available);

        $this->auth()->postJson('/api/v1/stock/movements/unblock', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 2,
            'reason' => 'Avaria descartada',
        ])->assertStatus(201)->assertJsonPath('data.type', 'unblock');

        $balance->refresh();
        $this->assertEquals(10, $balance->quantity_on_hand);
        $this->assertEquals(2, $balance->quantity_blocked);
        $this->assertEquals(8, $balance->quantity_available);
    }

    #[Test]
    public function reserve_and_reserve_cancel_only_change_reserved_quantity(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'reserve');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 10,
            'reason' => 'Entrada',
        ])->assertStatus(201);

        $reserveResponse = $this->auth()->postJson('/api/v1/stock/movements/reserve', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 5,
            'reason' => 'Pedido #1',
        ]);

        $reserveResponse->assertStatus(201)->assertJsonPath('data.type', 'reserve');
        $reserveUuid = $reserveResponse->json('data.uuid');

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(10, $balance->quantity_on_hand);
        $this->assertEquals(5, $balance->quantity_reserved);
        $this->assertEquals(5, $balance->quantity_available);

        $this->auth()->postJson('/api/v1/stock/movements/reserve-cancel', [
            'movement_uuid' => $reserveUuid,
        ])->assertStatus(201)->assertJsonPath('data.type', 'reserve_cancel');

        $balance->refresh();
        $this->assertEquals(10, $balance->quantity_on_hand);
        $this->assertEquals(0, $balance->quantity_reserved);
        $this->assertEquals(10, $balance->quantity_available);
    }

    #[Test]
    public function reserve_cancel_cannot_be_applied_twice_and_keeps_other_reservation_intact(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'reserve');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 20,
            'reason' => 'Entrada',
        ])->assertStatus(201);

        $reserve1 = $this->auth()->postJson('/api/v1/stock/movements/reserve', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 5,
            'reason' => 'Pedido #1',
        ]);
        $reserve1Uuid = $reserve1->assertStatus(201)->json('data.uuid');

        $reserve2 = $this->auth()->postJson('/api/v1/stock/movements/reserve', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 7,
            'reason' => 'Pedido #2',
        ]);
        $reserve2->assertStatus(201);

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(12, $balance->quantity_reserved);

        $this->auth()->postJson('/api/v1/stock/movements/reserve-cancel', [
            'movement_uuid' => $reserve1Uuid,
        ])->assertStatus(201)->assertJsonPath('data.type', 'reserve_cancel');

        $balance->refresh();
        $this->assertEquals(7, $balance->quantity_reserved);

        $this->auth()->postJson('/api/v1/stock/movements/reserve-cancel', [
            'movement_uuid' => $reserve1Uuid,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_STOCK_MOVEMENT');

        $balance->refresh();
        $this->assertEquals(7, $balance->quantity_reserved, 'A segunda reserva não pode ser afetada pela tentativa de cancelamento duplicado.');
    }

    #[Test]
    public function reserve_cancel_rejects_movement_that_is_not_a_reserve(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'reserve');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $entryResponse = $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 10,
            'reason' => 'Entrada',
        ]);
        $entryUuid = $entryResponse->json('data.uuid');

        $this->auth()->postJson('/api/v1/stock/movements/reserve-cancel', [
            'movement_uuid' => $entryUuid,
        ])->assertStatus(422);
    }

    #[Test]
    public function quantity_available_accounts_for_reserved_and_blocked_together(): void
    {
        $this->grantPermission('stock', 'entry');
        $this->grantPermission('stock', 'block');
        $this->grantPermission('stock', 'reserve');
        $product = $this->createProduct($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 20,
            'reason' => 'Entrada',
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/stock/movements/block', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 5,
            'reason' => 'Avaria',
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/stock/movements/reserve', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 3,
            'reason' => 'Pedido',
        ])->assertStatus(201);

        $balance = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(20, $balance->quantity_on_hand);
        $this->assertEquals(5, $balance->quantity_blocked);
        $this->assertEquals(3, $balance->quantity_reserved);
        $this->assertEquals(12, $balance->quantity_available);
    }

    #[Test]
    public function entry_rejects_product_from_another_tenant(): void
    {
        $this->grantPermission('stock', 'entry');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignProduct = $this->createProduct($otherTenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $foreignProduct->uuid,
            'location_uuid' => $location->uuid,
            'quantity' => 1,
            'reason' => 'Teste IDOR',
        ])->assertStatus(422);
    }

    #[Test]
    public function entry_rejects_location_from_another_tenant(): void
    {
        $this->grantPermission('stock', 'entry');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $product = $this->createProduct($this->tenant->id);
        $foreignLocation = $this->createLocation($otherTenant->id);

        $this->auth()->postJson('/api/v1/stock/movements/entry', [
            'product_uuid' => $product->uuid,
            'location_uuid' => $foreignLocation->uuid,
            'quantity' => 1,
            'reason' => 'Teste IDOR',
        ])->assertStatus(422);
    }

    #[Test]
    public function last_purchase_cost_is_omitted_without_view_costs_permission(): void
    {
        $this->grantPermission('products', 'read');

        $product = $this->createProduct($this->tenant->id, ['last_purchase_cost' => 12.5]);

        $response = $this->auth()->getJson('/api/v1/products/' . $product->uuid);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('last_purchase_cost', $response->json('data'));
    }

    #[Test]
    public function last_purchase_cost_is_included_with_view_costs_permission(): void
    {
        $this->grantPermission('products', 'read');
        $this->grantPermission('stock', 'view_costs');

        $product = $this->createProduct($this->tenant->id, ['last_purchase_cost' => 12.5]);

        $response = $this->auth()->getJson('/api/v1/products/' . $product->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.last_purchase_cost', 12.5);
    }
}
