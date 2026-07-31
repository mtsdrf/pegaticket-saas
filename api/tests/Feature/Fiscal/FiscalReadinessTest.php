<?php

namespace Tests\Feature\Fiscal;

use App\Models\Client\Client;
use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Fiscal\TaxRule;
use App\Models\Location\Endereco;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class FiscalReadinessTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantScopedUser('fiscal-readiness@example.com');
        $this->grantPermission('tax-rules', 'read');
    }

    #[Test]
    public function readiness_highlights_missing_fiscal_foundations(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/fiscal-readiness')
            ->assertOk()
            ->assertJsonPath('data.status', 'attention');
    }

    #[Test]
    public function readiness_returns_ready_when_foundations_are_filled(): void
    {
        $this->tenant->update([
            'cnpj' => '12345678000199',
            'ie' => '123456789',
            'tax_regime' => 'simples_nacional',
            'fiscal_environment' => 'homologacao',
            'ibge_city_code' => '3550308',
            'fiscal_nfce_csc_id' => '000001',
            'fiscal_nfce_csc_code' => 'token-seguro',
        ]);

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda NFC-e',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'is_active' => true,
        ]);

        TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'tax_type' => 'icms',
            'rate_percent' => 18,
            'is_active' => true,
        ]);

        $productCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Categoria fiscal',
            'priority' => 1,
            'is_active' => true,
        ]);

        $productType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $productCategory->id,
            'name' => 'Tipo fiscal',
            'priority' => 1,
            'is_active' => true,
        ]);

        Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_type_id' => $productType->id,
            'name' => 'Produto fiscal',
            'price' => 10,
            'unit' => 'UN',
            'ncm' => '22030000',
            'origin' => '0',
            'default_cfop' => '5102',
            'csosn_cst' => '102',
            'is_available' => true,
        ]);

        $estadoId = DB::table('estados')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sao Paulo',
            'uf' => 'SP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cidadeId = DB::table('cidades')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estadoId,
            'name' => 'Sao Paulo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bairroId = DB::table('bairros')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidadeId,
            'name' => 'Centro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'estado_id' => $estadoId,
            'cidade_id' => $cidadeId,
            'bairro_id' => $bairroId,
            'logradouro' => 'Rua Fiscal',
            'is_active' => true,
        ]);

        Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $endereco->id,
            'name' => 'Cliente fiscal',
            'cpf_cnpj' => '12345678901',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/fiscal-readiness')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.score_percent', 100);
    }

    #[Test]
    public function readiness_highlights_missing_nfce_csc_when_nfce_profile_is_active(): void
    {
        $this->tenant->update([
            'cnpj' => '12345678000199',
            'ie' => '123456789',
            'tax_regime' => 'simples_nacional',
            'fiscal_environment' => 'homologacao',
            'ibge_city_code' => '3550308',
            'fiscal_provider' => 'focus_nfe',
            'fiscal_provider_api_token' => 'token-api',
        ]);

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda NFC-e',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/fiscal-readiness')
            ->assertOk()
            ->assertJsonPath('data.status', 'attention');

        $checks = collect($response->json('data.checks'));

        $this->assertTrue($checks->contains(
            fn (array $check) => $check['key'] === 'provider' && str_contains($check['details'], 'CSC')
        ));
    }
}
