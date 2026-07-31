<?php

namespace Tests\Feature\Fiscal;

use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Fiscal\TaxRule;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class FiscalModuleTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected ProductType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('fiscal-module@test.com');

        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Doces',
            'is_active' => true,
        ]);

        $this->type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $category->id,
            'name' => 'Padaria',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_access_fiscal_endpoints(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tax-rules')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/fiscal-operation-profiles')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/fiscal-readiness')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_permission_can_manage_tax_rules(): void
    {
        $this->grantPermission('tax-rules', 'read');
        $this->grantPermission('tax-rules', 'create');
        $this->grantPermission('tax-rules', 'update');
        $this->grantPermission('tax-rules', 'delete');

        $create = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tax-rules', [
                'tax_type' => 'icms',
                'rate_percent' => 18,
                'scope' => ['cfop' => ['5102'], 'uf_origin' => ['SP']],
                'valid_from' => '2026-01-01',
                'is_active' => true,
            ])
            ->assertStatus(201);

        $uuid = $create->json('data.uuid');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tax-rules')
            ->assertOk()
            ->assertJsonPath('data.0.scope.cfop.0', '5102');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/tax-rules/{$uuid}", [
                'rate_percent' => 17,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.rate_percent', 17)
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/tax-rules/{$uuid}")
            ->assertStatus(204);

        $this->assertSoftDeleted('tax_rules', ['uuid' => $uuid]);
    }

    #[Test]
    public function user_with_permission_can_manage_fiscal_operation_profiles(): void
    {
        $this->grantPermission('tax-rules', 'read');
        $this->grantPermission('tax-rules', 'create');
        $this->grantPermission('tax-rules', 'update');
        $this->grantPermission('tax-rules', 'delete');

        $create = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/fiscal-operation-profiles', [
                'name' => 'Venda interna NFC-e',
                'operation_nature' => 'sale',
                'document_type' => 'nfce',
                'default_cfop' => '5102',
                'scope' => [
                    'order_origin' => ['staff'],
                    'fulfillment_type' => ['pickup'],
                    'destination_type' => ['consumer_final'],
                ],
                'description' => 'Perfil base',
                'is_active' => true,
            ])
            ->assertStatus(201);

        $uuid = $create->json('data.uuid');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/fiscal-operation-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Venda interna NFC-e');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/fiscal-operation-profiles/{$uuid}", [
                'description' => 'Perfil atualizado',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Perfil atualizado');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/fiscal-operation-profiles/{$uuid}")
            ->assertStatus(204);

        $this->assertSoftDeleted('fiscal_operation_profiles', ['uuid' => $uuid]);
    }

    #[Test]
    public function fiscal_readiness_summarizes_company_products_rules_and_profiles(): void
    {
        $this->grantPermission('tax-rules', 'read');

        $this->tenant->update([
            'cnpj' => '12345678000199',
            'tax_regime' => 'simples_nacional',
            'ibge_city_code' => '3550308',
        ]);

        Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_type_id' => $this->type->id,
            'name' => 'Bolo de milho',
            'price' => 22.50,
            'ncm' => '19059090',
            'origin' => '0',
            'default_cfop' => '5102',
            'csosn_cst' => '102',
            'is_available' => true,
        ]);

        TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'tax_type' => 'pis',
            'rate_percent' => 1.65,
            'scope' => ['ncm' => ['19059090']],
            'is_active' => true,
        ]);

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venda interna NFC-e',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'default_cfop' => '5102',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/fiscal-readiness')
            ->assertOk();

        $this->assertEquals('attention', $response->json('data.status'));
        $this->assertEquals('Cadastro da empresa', $response->json('data.checks.0.label'));
        $this->assertEquals('Produtos fiscais', $response->json('data.checks.1.label'));
        $this->assertEquals('Regras e perfis fiscais', $response->json('data.checks.2.label'));
    }
}
