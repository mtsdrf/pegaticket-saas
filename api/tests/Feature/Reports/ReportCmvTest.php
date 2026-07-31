<?php

namespace Tests\Feature\Reports;

use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockLocation;
use App\Models\Stock\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Roadmap A3.13 — CMV real daqui pra frente. Ver architecture-decisions.md
 * (não há retroatividade: produto sem entrada com unit_cost preenchido
 * antes da mudança fica sem CMV, `has_cost_data=false`).
 */
class ReportCmvTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('report-cmv-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function entry(Product $product, StockLocation $location, float $quantity, ?float $unitCost, string $reason): void
    {
        $balance = StockBalance::firstOrCreate(
            [
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'location_id' => $location->id,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_blocked' => 0,
            ]
        );

        $before = (float) $balance->quantity_on_hand;
        $after = $before + $quantity;

        $balance->update([
            'quantity_on_hand' => $after,
        ]);

        StockMovement::create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'type' => 'entry',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_before' => $before,
            'balance_after' => $after,
            'reason' => $reason,
        ]);
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
            'price' => 20,
            'is_available' => true,
            'stock_quantity' => 0,
        ], $overrides));
    }

    protected function createLocation(int $tenantId): StockLocation
    {
        return StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Location ' . Str::random(6),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function product_with_single_cost_entry_shows_that_cost_as_cmv(): void
    {
        $this->grantPermission('reports', 'read');
        $product = $this->createProduct($this->tenant->id, ['price' => 20]);
        $location = $this->createLocation($this->tenant->id);

        $this->entry($product, $location, 10, 8.00, 'Compra inicial');

        $response = $this->auth()->getJson('/api/v1/reports/cmv')->assertStatus(200);

        $item = collect($response->json('data'))->firstWhere('product_uuid', $product->uuid);
        $this->assertNotNull($item);
        $this->assertEquals('8.00', $item['cmv']);
        $this->assertEquals('20.00', $item['sale_price']);
        $this->assertTrue($item['has_cost_data']);
        $this->assertEquals(60.0, $item['margin_percent']);
    }

    #[Test]
    public function product_with_multiple_cost_entries_computes_weighted_average(): void
    {
        $this->grantPermission('reports', 'read');
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);
        $location = $this->createLocation($this->tenant->id);

        // (10*4 + 30*8) / (10+30) = (40+240)/40 = 7.00
        $this->entry($product, $location, 10, 4.00, 'Compra 1');
        $this->entry($product, $location, 30, 8.00, 'Compra 2');

        $response = $this->auth()->getJson('/api/v1/reports/cmv')->assertStatus(200);

        $item = collect($response->json('data'))->firstWhere('product_uuid', $product->uuid);
        $this->assertEquals('7.00', $item['cmv']);
        $this->assertTrue($item['has_cost_data']);
    }

    #[Test]
    public function product_without_any_costed_entry_has_null_cmv(): void
    {
        $this->grantPermission('reports', 'read');
        $product = $this->createProduct($this->tenant->id, ['price' => 15]);
        $location = $this->createLocation($this->tenant->id);

        // Entrada sem custo informado não deve contar como zero.
        $this->entry($product, $location, 5, null, 'Compra sem custo');

        $response = $this->auth()->getJson('/api/v1/reports/cmv')->assertStatus(200);

        $item = collect($response->json('data'))->firstWhere('product_uuid', $product->uuid);
        $this->assertNotNull($item);
        $this->assertNull($item['cmv']);
        $this->assertNull($item['margin_percent']);
        $this->assertFalse($item['has_cost_data']);
    }

    #[Test]
    public function endpoint_requires_reports_read_permission(): void
    {
        $this->auth()->getJson('/api/v1/reports/cmv')->assertStatus(403);
    }
}
