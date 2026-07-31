<?php

namespace Tests\Feature\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Services\Product\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('product-import-user@test.com');
        $this->grantPermission('products', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function csvFile(string $content, string $name = 'produtos.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    #[Test]
    public function preview_parses_valid_and_invalid_rows_without_persisting_anything(): void
    {
        $csv = "nome,categoria,tipo,preco,descricao,sku,disponivel\n"
            . "Coca-Cola 2L,Bebidas,Refrigerante,12.90,Garrafa 2 litros,SKU-001,sim\n"
            . ",Bebidas,Refrigerante,10.00,Sem nome,,sim\n"
            . "Suco de Laranja,Bebidas,Refrigerante,abc,Preço inválido,,sim\n";

        $response = $this->auth()
            ->post('/api/v1/products/import/preview', [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(3, $data['total']);
        $this->assertEquals(1, $data['valid_count']);
        $this->assertEquals(2, $data['error_count']);
        $this->assertTrue($data['rows'][0]['category_will_be_created']);
        $this->assertTrue($data['rows'][0]['type_will_be_created']);
        $this->assertEquals('error', $data['rows'][1]['status']);
        $this->assertEquals('error', $data['rows'][2]['status']);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_types', 0);
        $this->assertDatabaseCount('product_categories', 0);
    }

    #[Test]
    public function preview_rejects_malformed_csv_missing_required_columns(): void
    {
        $csv = "nome,preco\nCoca-Cola,12.90\n";

        $this->auth()
            ->post('/api/v1/products/import/preview', [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_IMPORT_FILE');
    }

    #[Test]
    public function preview_rejects_empty_file(): void
    {
        $this->auth()
            ->post('/api/v1/products/import/preview', [
                'file' => $this->csvFile(''),
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function preview_rejects_file_exceeding_row_limit(): void
    {
        $lines = ["nome,categoria,tipo,preco,descricao,sku,disponivel"];

        for ($i = 0; $i < ProductImportService::MAX_ROWS + 1; $i++) {
            $lines[] = "Produto {$i},Categoria,Tipo,10.00,,,";
        }

        $csv = implode("\n", $lines) . "\n";

        $this->auth()
            ->post('/api/v1/products/import/preview', [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PRODUCT_IMPORT_LIMIT_EXCEEDED');
    }

    #[Test]
    public function preview_supports_semicolon_delimiter(): void
    {
        $csv = "nome;categoria;tipo;preco;descricao;sku;disponivel\n"
            . "Coca-Cola 2L;Bebidas;Refrigerante;12,90;Garrafa;SKU-100;sim\n";

        $response = $this->auth()
            ->post('/api/v1/products/import/preview', [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(1, $data['valid_count']);
        $this->assertEquals(12.9, $data['rows'][0]['preco']);
    }

    #[Test]
    public function commit_creates_products_and_missing_categories_and_types_in_one_transaction(): void
    {
        $rows = [
            ['nome' => 'Coca-Cola 2L', 'categoria' => 'Bebidas', 'tipo' => 'Refrigerante', 'preco' => '12.90', 'descricao' => 'Garrafa', 'sku' => 'SKU-201', 'disponivel' => 'sim'],
            ['nome' => 'Guaraná 2L', 'categoria' => 'Bebidas', 'tipo' => 'Refrigerante', 'preco' => '11,50', 'descricao' => null, 'sku' => 'SKU-202', 'disponivel' => 'não'],
        ];

        $response = $this->auth()
            ->postJson('/api/v1/products/import/commit', ['rows' => $rows])
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(2, $data['created_count']);
        $this->assertEquals(0, $data['skipped_count']);
        $this->assertEquals(1, $data['categories_created_count']);
        $this->assertEquals(1, $data['types_created_count']);

        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_types', 1);
        $this->assertDatabaseCount('product_categories', 1);

        $category = ProductCategory::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals('Bebidas', $category->name);

        $type = ProductType::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals('Refrigerante', $type->name);
        $this->assertEquals($category->id, $type->product_category_id);

        $unavailable = Product::where('sku', 'SKU-202')->first();
        $this->assertFalse((bool) $unavailable->is_available);

        $this->assertDatabaseHas('audit_logs', ['event' => 'product_import_committed']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'product_created']);
    }

    #[Test]
    public function commit_reuses_existing_category_and_type_by_name(): void
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Bebidas',
            'is_active' => true,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $category->id,
            'name' => 'Refrigerante',
            'is_active' => true,
        ]);

        $rows = [
            ['nome' => 'Coca-Cola 2L', 'categoria' => null, 'tipo' => 'refrigerante', 'preco' => '12.90'],
        ];

        $response = $this->auth()
            ->postJson('/api/v1/products/import/commit', ['rows' => $rows])
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(1, $data['created_count']);
        $this->assertEquals(0, $data['categories_created_count']);
        $this->assertEquals(0, $data['types_created_count']);

        $this->assertDatabaseCount('product_categories', 1);
        $this->assertDatabaseCount('product_types', 1);

        $product = Product::first();
        $this->assertEquals($type->id, $product->product_type_id);
    }

    #[Test]
    public function commit_skips_rows_that_fail_revalidation_even_if_frontend_resent_them_as_valid(): void
    {
        $rows = [
            ['nome' => 'Coca-Cola 2L', 'categoria' => 'Bebidas', 'tipo' => 'Refrigerante', 'preco' => '12.90'],
            // linha inválida (sem nome) reenviada mesmo assim pelo cliente —
            // o backend precisa revalidar e pular, nunca confiar só no
            // preview.
            ['nome' => '', 'categoria' => 'Bebidas', 'tipo' => 'Refrigerante', 'preco' => '10.00'],
            // preço inválido
            ['nome' => 'Produto Ruim', 'categoria' => 'Bebidas', 'tipo' => 'Refrigerante', 'preco' => 'abc'],
        ];

        $response = $this->auth()
            ->postJson('/api/v1/products/import/commit', ['rows' => $rows])
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(1, $data['created_count']);
        $this->assertEquals(2, $data['skipped_count']);
        $this->assertDatabaseCount('products', 1);

        $this->assertEquals('skipped', $data['rows'][1]['status']);
        $this->assertNotEmpty($data['rows'][1]['errors']);
        $this->assertEquals('skipped', $data['rows'][2]['status']);
    }

    #[Test]
    public function commit_rejects_duplicate_sku_against_existing_product_in_tenant(): void
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Bebidas',
            'is_active' => true,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $category->id,
            'name' => 'Refrigerante',
            'is_active' => true,
        ]);

        Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_type_id' => $type->id,
            'name' => 'Já existente',
            'price' => 5.00,
            'sku' => 'SKU-DUP',
            'is_available' => true,
        ]);

        $rows = [
            ['nome' => 'Novo Produto', 'categoria' => 'Bebidas', 'tipo' => 'Refrigerante', 'preco' => '9.90', 'sku' => 'SKU-DUP'],
        ];

        $response = $this->auth()
            ->postJson('/api/v1/products/import/commit', ['rows' => $rows])
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(0, $data['created_count']);
        $this->assertEquals(1, $data['skipped_count']);
        $this->assertDatabaseCount('products', 1);
    }

    #[Test]
    public function commit_rejects_payload_exceeding_row_limit(): void
    {
        $rows = [];

        for ($i = 0; $i < ProductImportService::MAX_ROWS + 1; $i++) {
            $rows[] = ['nome' => "Produto {$i}", 'categoria' => 'Bebidas', 'tipo' => 'Refrigerante', 'preco' => '10.00'];
        }

        $this->auth()
            ->postJson('/api/v1/products/import/commit', ['rows' => $rows])
            ->assertStatus(422);
    }

    #[Test]
    public function commit_conflicting_category_for_same_new_type_across_rows_is_skipped(): void
    {
        $rows = [
            ['nome' => 'Produto A', 'categoria' => 'Bebidas', 'tipo' => 'Novo Tipo', 'preco' => '10.00'],
            ['nome' => 'Produto B', 'categoria' => 'Outra Categoria', 'tipo' => 'Novo Tipo', 'preco' => '11.00'],
        ];

        $response = $this->auth()
            ->postJson('/api/v1/products/import/commit', ['rows' => $rows])
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(1, $data['created_count']);
        $this->assertEquals(1, $data['skipped_count']);
        $this->assertEquals('skipped', $data['rows'][1]['status']);
    }

    #[Test]
    public function user_without_permission_cannot_preview_or_commit_import(): void
    {
        $this->setUpTenantScopedUser('product-import-noperm@test.com');

        $this->auth()
            ->post('/api/v1/products/import/preview', [
                'file' => $this->csvFile("nome,tipo,preco\nA,B,1.00\n"),
            ])
            ->assertStatus(403);

        $this->auth()
            ->postJson('/api/v1/products/import/commit', ['rows' => [
                ['nome' => 'A', 'tipo' => 'B', 'categoria' => 'C', 'preco' => '1.00'],
            ]])
            ->assertStatus(403);
    }
}
