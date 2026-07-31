<?php

namespace Tests\Feature\Fiscal;

use App\Models\Client\Client;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Location\Endereco;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garante que os campos fiscais (roadmap Fiscal D0) são de fato nullable:
 * criar tenant/produto/cliente SEM nenhum campo fiscal continua funcionando,
 * sem quebrar o cadastro existente.
 */
class FiscalFieldsNullableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tenant_can_be_created_without_fiscal_fields_and_defaults_to_homologacao(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'No Fiscal Tenant',
            'slug' => 'nofiscal-' . Str::random(8),
            'is_active' => true,
        ]);

        $fresh = $tenant->fresh();
        $this->assertNull($fresh->cnpj);
        $this->assertNull($fresh->ie);
        $this->assertNull($fresh->tax_regime);
        // fiscal_environment NUNCA default producao.
        $this->assertSame('homologacao', $fresh->fiscal_environment);
    }

    #[Test]
    public function product_can_be_created_without_fiscal_fields(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'T',
            'slug' => 't-' . Str::random(8),
            'is_active' => true,
        ]);

        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Categoria',
            'is_active' => true,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_category_id' => $category->id,
            'name' => 'Tipo',
            'is_active' => true,
        ]);

        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_type_id' => $type->id,
            'name' => 'Produto sem fiscal',
            'price' => 10.0,
            'unit' => 'un',
            'is_available' => true,
        ]);

        $fresh = $product->fresh();
        $this->assertNull($fresh->ncm);
        $this->assertNull($fresh->cest);
        $this->assertNull($fresh->origin);
        $this->assertNull($fresh->default_cfop);
        $this->assertNull($fresh->csosn_cst);
    }

    #[Test]
    public function client_can_be_created_without_fiscal_fields(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'T',
            'slug' => 't-' . Str::random(8),
            'is_active' => true,
        ]);

        $estadoId = DB::table('estados')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado X',
            'uf' => 'SP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cidadeId = DB::table('cidades')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estadoId,
            'name' => 'Cidade X',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bairroId = DB::table('bairros')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidadeId,
            'name' => 'Bairro X',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'estado_id' => $estadoId,
            'cidade_id' => $cidadeId,
            'bairro_id' => $bairroId,
            'logradouro' => 'Rua Teste',
            'is_active' => true,
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'endereco_id' => $endereco->id,
            'name' => 'Cliente sem fiscal',
            'is_trusted' => true,
            'is_active' => true,
        ]);

        $fresh = $client->fresh();
        $this->assertNull($fresh->cpf_cnpj);
        $this->assertNull($fresh->ie);
        $this->assertNull($fresh->ie_indicator);
    }
}
