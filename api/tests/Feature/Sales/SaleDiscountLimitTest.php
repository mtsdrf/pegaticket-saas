<?php

namespace Tests\Feature\Sales;

use App\Models\Tenant\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Roadmap A1.5 — limite percentual de desconto por perfil. Único ponto de
 * entrada real de "desconto manual" mapeado no código: override de
 * items[].unit_price abaixo do preço resolvido, em SaleService::create()
 * (fluxo interno atual, incluindo origem legada normalizada) e
 * SaleService::updateItems() (edição de pedido já criado).
 * Ver architecture-decisions.md pra investigação completa (coupon-based
 * discount do storefront não passa por aqui — é ator FinalCustomer, sem
 * TenantRole).
 */
class SaleDiscountLimitTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);

        $this->setUpTenantScopedUser('discount-limit-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Configura discount_limit_percent na linha tenant_role_permissions do
     * papel do usuário logado, pra functionality 'sales' — mesma linha
     * que PermissionService::resolveOrderDiscountLimitPercent lê.
     */
    protected function setDiscountLimit(float $percent): void
    {
        $role = TenantRole::where('tenant_id', $this->tenant->id)->firstOrFail();

        $funcId = DB::table('functionalities')->where('slug', 'sales')->value('id')
            ?? DB::table('functionalities')->insertGetId([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Pedidos',
                'slug' => 'sales',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $actionId = DB::table('actions')->where('key', 'create')->value('id')
            ?? DB::table('actions')->insertGetId([
                'key' => 'create',
                'name' => 'Criar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('tenant_role_permissions')->updateOrInsert(
            [
                'tenant_role_id' => $role->id,
                'functionality_id' => $funcId,
                'action_id' => $actionId,
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'discount_limit_percent' => $percent,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    #[Test]
    public function manual_unit_price_discount_above_the_profile_limit_is_rejected_on_create(): void
    {
        $this->setDiscountLimit(10);

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 100]);

        // 15% de desconto (unit_price 85 sobre um preço de 100) excede o
        // limite de 10% configurado.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1, 'unit_price' => 85],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'DISCOUNT_LIMIT_EXCEEDED');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function manual_unit_price_discount_within_the_profile_limit_is_accepted(): void
    {
        $this->setDiscountLimit(10);

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 100]);

        // 8% de desconto, dentro do limite de 10%.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1, 'unit_price' => 92],
            ],
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function manual_unit_price_above_catalog_price_is_never_blocked_as_discount(): void
    {
        $this->setDiscountLimit(5);

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 100]);

        // unit_price maior que o preço de catálogo é acréscimo, não
        // desconto — nunca bloqueado por discount_limit_percent.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1, 'unit_price' => 150],
            ],
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function no_discount_limit_configured_preserves_unrestricted_behavior(): void
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 100]);

        // Sem discount_limit_percent configurado (default), qualquer
        // desconto manual continua livre — comportamento anterior à feature.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1, 'unit_price' => 1],
            ],
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function manual_unit_price_discount_above_the_profile_limit_is_rejected_on_update_items(): void
    {
        $this->setDiscountLimit(10);

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 100]);

        $created = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        $itemUuid = $created['items'][0]['uuid'];

        $response = $this->auth()->putJson("/api/v1/sales/{$created['uuid']}/items", [
            'items' => [
                ['uuid' => $itemUuid, 'ticket_type_uuid' => $product->uuid, 'quantity' => 1, 'unit_price' => 80],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'DISCOUNT_LIMIT_EXCEEDED');
    }
}
