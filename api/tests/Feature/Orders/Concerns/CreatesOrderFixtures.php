<?php

namespace Tests\Feature\Orders\Concerns;

use App\Models\Client\Client;
use App\Models\Client\ClientCategory;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\GeneratesUniqueUf;

/**
 * Extraído de OrderTest.php (2026-07-12) pra ser reaproveitado também por
 * OrderInstallmentTest.php, sem duplicar os ~80 linhas de fixture de
 * Client/Product/StockLocation — mesma ideia de SetsUpTenantScopedUser,
 * só que específico da árvore de fixtures de Pedido.
 */
trait CreatesOrderFixtures
{
    use GeneratesUniqueUf;

    protected function createLocation(int $tenantId, array $overrides = []): StockLocation
    {
        return StockLocation::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Location ' . Str::random(6),
            'is_active' => true,
        ], $overrides));
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
        ], $overrides));
    }

    protected function createClient(int $tenantId): Client
    {
        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado ' . Str::random(6),
            'uf' => $this->nextUf(),
        ]);

        $cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => 'Cidade ' . Str::random(6),
        ]);

        $bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidade->id,
            'name' => 'Bairro ' . Str::random(6),
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua Teste, 123',
            'is_active' => true,
        ]);

        return Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'endereco_id' => $endereco->id,
            'name' => 'Client ' . Str::random(6),
            'is_trusted' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Categoria de cliente + preço de categoria por produto, usados pelos
     * testes de resolução de preço (ProductPricingService) dentro da
     * criação de pedido — ver ProductCategoryPriceTest.php para a
     * cobertura dedicada do endpoint de sync.
     */
    protected function createClientCategory(int $tenantId, array $overrides = []): ClientCategory
    {
        return ClientCategory::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Client Category ' . Str::random(6),
            'is_active' => true,
        ], $overrides));
    }

    protected function attachClientCategory(Client $client, ClientCategory $category): void
    {
        DB::table('client_client_categories')->insert([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'client_category_id' => $category->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function setProductCategoryPrice(Product $product, ClientCategory $category, float $price): void
    {
        DB::table('product_category_prices')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'client_category_id' => $category->id,
            'price' => $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function stockEntry(int $tenantId, Product $product, StockLocation $location, int $quantity): void
    {
        StockBalance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'location_id' => $location->id,
            ],
            [
                'quantity_on_hand' => $quantity,
                'quantity_reserved' => 0,
                'quantity_blocked' => 0,
            ]
        );
    }
}
