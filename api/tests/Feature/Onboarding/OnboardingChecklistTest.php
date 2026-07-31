<?php

namespace Tests\Feature\Onboarding;

use App\Models\Client\Client;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Storefront\StoreBusinessHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('onboarding-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function checklist_starts_fully_pending_for_a_new_tenant(): void
    {
        $response = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertFalse($data['has_product']);
        $this->assertFalse($data['has_client']);
        $this->assertFalse($data['has_first_order']);
        $this->assertFalse($data['is_dismissed']);
        $this->assertNull($data['dismissed_at']);
        $this->assertEquals(0, $data['completed']);
        $this->assertEquals(3, $data['total']);
        $this->assertSame(['has_product', 'has_client', 'has_first_order'], array_column($data['steps'], 'key'));
    }

    #[Test]
    public function checklist_reflects_existing_product_and_client(): void
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
            'name' => 'Coca-Cola',
            'price' => 10.00,
            'is_available' => true,
        ]);

        Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Teste',
            'is_active' => true,
        ]);

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($data['has_product']);
        $this->assertTrue($data['has_client']);
        $this->assertFalse($data['has_first_order']);
        $this->assertEquals(2, $data['completed']);
    }

    #[Test]
    public function checklist_includes_storefront_steps_when_the_plan_allows_storefront(): void
    {
        $planId = DB::table('plans')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Plano Storefront',
            'slug' => 'plano-storefront-' . Str::random(6),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $functionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Loja online',
            'slug' => 'storefront',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $planId,
            'functionality_id' => $functionalityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenant->update(['plan_id' => $planId]);

        StoreBusinessHour::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 1,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'is_closed' => false,
        ]);

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayHasKey('has_store_address', $data);
        $this->assertTrue($data['storefront_configured']);
        $this->assertContains('has_store_address', array_column($data['steps'], 'key'));
        $this->assertContains('storefront_configured', array_column($data['steps'], 'key'));
    }

    #[Test]
    public function checklist_has_store_address_is_true_once_tenant_endereco_id_is_set(): void
    {
        $planId = DB::table('plans')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Plano Storefront',
            'slug' => 'plano-storefront-' . Str::random(6),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $functionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Loja online',
            'slug' => 'storefront',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $planId,
            'functionality_id' => $functionalityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenant->update(['plan_id' => $planId]);

        $estado = Estado::create(['uuid' => (string) Str::uuid(), 'name' => 'São Paulo', 'uf' => 'SP', 'is_active' => true]);
        $cidade = Cidade::create(['uuid' => (string) Str::uuid(), 'estado_id' => $estado->id, 'name' => 'Campinas', 'is_active' => true]);
        $bairro = Bairro::create(['uuid' => (string) Str::uuid(), 'cidade_id' => $cidade->id, 'name' => 'Cambuí', 'is_active' => true]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua da Loja',
            'is_active' => true,
        ]);
        $this->tenant->update(['endereco_id' => $endereco->id]);

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($data['has_store_address']);
    }

    #[Test]
    public function checklist_can_be_dismissed_and_restored_per_tenant_user(): void
    {
        $dismissed = $this->auth()
            ->postJson('/api/v1/onboarding/checklist/dismiss')
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($dismissed['is_dismissed']);
        $this->assertNotNull($dismissed['dismissed_at']);

        $restored = $this->auth()
            ->deleteJson('/api/v1/onboarding/checklist/dismiss')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($restored['is_dismissed']);
        $this->assertNull($restored['dismissed_at']);
    }

    #[Test]
    public function checklist_ignores_soft_deleted_records(): void
    {
        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Removido',
            'is_active' => true,
        ]);
        $client->delete();

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($data['has_client']);
    }

    #[Test]
    public function checklist_is_scoped_per_tenant(): void
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
            'name' => 'Coca-Cola',
            'price' => 10.00,
            'is_available' => true,
        ]);

        // Novo tenant/usuário (setUpTenantScopedUser cria um Tenant novo a
        // cada chamada) — reaproveita o helper em vez de montar um segundo
        // tenant/vínculo manualmente.
        $this->setUpTenantScopedUser('onboarding-other-user@test.com');

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($data['has_product']);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        // SetsUpTenantScopedUser::setUpTenantScopedUser() troca de tenant
        // via $this->withHeader(...)->postJson(...), o que deixa o
        // Authorization Bearer setado em $this->defaultHeaders pro RESTO
        // do teste (withHeader() não é "por chamada", é acumulativo na
        // instância) — sem remover aqui, esta requisição "sem auth"
        // sairia autenticada por acidente.
        $this->withoutHeader('Authorization')
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(401);
    }
}
