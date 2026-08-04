<?php

namespace Tests\Feature\Affiliate;

use App\Events\Sale\SalePaid;
use App\Models\Affiliate\Affiliate;
use App\Models\Affiliate\AffiliateCommission;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Inventory\InventoryHold;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\TenantSettings;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Fase 6 (roadmap 2026-08-02), primeira fatia — afiliado/promotor com
 * link rastreável, atribuição de venda e comissão. Cobre: CRUD de
 * afiliado, captura do tracking_code na criação do hold, atribuição da
 * Sale (via hold e via affiliate_code direto no checkout), geração da
 * comissão quando a Sale é paga (SalePaid), e isolamento multi-tenant.
 */
class AffiliateTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('affiliates@test.com');
    }

    private function authenticatedCustomer(string $email = 'comprador-afiliado@test.com'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    #[Test]
    public function staff_creates_and_updates_an_affiliate(): void
    {
        $this->grantPermission('affiliates', 'create');
        $this->grantPermission('affiliates', 'read');
        $this->grantPermission('affiliates', 'update');

        $created = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/affiliates', [
                'name' => 'Promotor Um',
                'email' => 'promotor1@test.com',
                'tracking_code' => 'PROMO1',
                'commission_percentage' => 10,
            ])->assertStatus(201)->json('data');

        $this->assertSame('PROMO1', $created['tracking_code']);
        $this->assertEquals(10.0, $created['commission_percentage']);
        $this->assertSame('active', $created['status']);
        $this->assertEquals(0.0, $created['commissions_total_amount']);

        $updated = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/affiliates/{$created['uuid']}", [
                'name' => 'Promotor Um Atualizado',
                'email' => 'promotor1@test.com',
                'tracking_code' => 'PROMO1',
                'commission_percentage' => 15,
                'status' => 'inactive',
            ])->assertStatus(200)->json('data');

        $this->assertSame('Promotor Um Atualizado', $updated['name']);
        $this->assertEquals(15.0, $updated['commission_percentage']);
        $this->assertSame('inactive', $updated['status']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/affiliates')
            ->assertStatus(200)
            ->assertJsonPath('data.0.uuid', $created['uuid']);
    }

    #[Test]
    public function creating_an_affiliate_without_permission_is_forbidden(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/affiliates', [
                'name' => 'Sem Permissao',
                'tracking_code' => 'NOPERM',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function tracking_code_is_unique_per_tenant(): void
    {
        $this->grantPermission('affiliates', 'create');

        Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existente',
            'tracking_code' => 'DUPLICADO',
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/affiliates', [
                'name' => 'Novo',
                'tracking_code' => 'DUPLICADO',
            ])->assertStatus(422);
    }

    #[Test]
    public function an_affiliate_from_another_tenant_is_not_visible(): void
    {
        $this->grantPermission('affiliates', 'read');

        $otherTenant = $this->createTenantWithStorefrontPlan(false);

        $otherAffiliate = Affiliate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'De Outro Tenant',
            'tracking_code' => 'OUTRO',
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/affiliates/{$otherAffiliate->uuid}")
            ->assertStatus(404);
    }

    #[Test]
    public function hold_creation_captures_the_affiliate_tracking_code_and_checkout_attributes_the_sale(): void
    {
        [, $customerToken] = $this->authenticatedCustomer();
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $affiliate = Affiliate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Promotora Loja',
            'tracking_code' => 'LOJAPROMO',
            'commission_percentage' => 8,
            'status' => 'active',
        ]);

        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 40]);
        $event = $ticketType->event;
        $sessionToken = 'sess-'.Str::random(10);

        $hold = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
                'session_token' => $sessionToken,
                'affiliate_code' => 'LOJAPROMO',
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
            ])->assertStatus(201)->json('data');

        $this->assertSame(
            $affiliate->id,
            InventoryHold::where('uuid', $hold['uuid'])->value('affiliate_id')
        );

        $checkout = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
                'hold_uuid' => $hold['uuid'],
                'session_token' => $sessionToken,
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Comprador',
                'client_last_name' => 'Teste',
                'client_phone' => '11999999999',
            ])->assertStatus(201)->json('data.sale');

        $sale = Sale::where('uuid', $checkout['uuid'])->firstOrFail();
        $this->assertSame($affiliate->id, $sale->affiliate_id);
    }

    #[Test]
    public function checkout_without_a_hold_attributes_the_sale_via_direct_affiliate_code(): void
    {
        [, $customerToken] = $this->authenticatedCustomer('sem-hold@test.com');
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $affiliate = Affiliate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Promotora Direta',
            'tracking_code' => 'DIRETO',
            'status' => 'active',
        ]);

        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 20]);

        $checkout = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
                'affiliate_code' => 'DIRETO',
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Comprador',
                'client_last_name' => 'Teste',
                'client_phone' => '11999999999',
            ])->assertStatus(201)->json('data.sale');

        $sale = Sale::where('uuid', $checkout['uuid'])->firstOrFail();
        $this->assertSame($affiliate->id, $sale->affiliate_id);
    }

    #[Test]
    public function paying_a_sale_with_an_affiliate_and_a_configured_percentage_generates_a_commission(): void
    {
        $affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Comissionada',
            'tracking_code' => 'COMISSAO',
            'commission_percentage' => 10,
            'status' => 'active',
        ]);

        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'affiliate_id' => $affiliate->id,
            'codigo' => 'SALE-AFF-1',
            'total_amount' => 200.0,
            'paid_amount' => 200.0,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 200,
            'line_total' => 200,
        ]);

        event(new SalePaid($sale->uuid, $this->userId));

        $commission = AffiliateCommission::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame($affiliate->id, $commission->affiliate_id);
        $this->assertSame('200.00', $commission->sale_amount);
        $this->assertSame('10.00', $commission->percentage_applied);
        $this->assertSame('20.00', $commission->amount);
        $this->assertSame(AffiliateCommission::STATUS_PENDING, $commission->status);

        // Reentrância do evento (mesmo cuidado de CreateReceivableOnSalePaid)
        // não deve duplicar a comissão.
        event(new SalePaid($sale->uuid, $this->userId));
        $this->assertSame(1, AffiliateCommission::where('sale_id', $sale->id)->count());

        $commissions = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/affiliates/{$affiliate->uuid}/commissions")
            ->assertStatus(403);
        // Sem permissão concedida ainda neste teste — 403 confirma que a
        // rota está protegida; cenário "com permissão" coberto abaixo.
        $this->assertSame(403, $commissions->getStatusCode());

        $this->grantPermission('affiliates', 'read');

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/affiliates/{$affiliate->uuid}/commissions")
            ->assertStatus(200)
            ->assertJsonPath('data.0.amount', 20);
    }

    #[Test]
    public function paying_a_sale_falls_back_to_the_tenant_default_commission_percentage_when_the_affiliate_has_none(): void
    {
        TenantSettings::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_default_commission_percentage' => 5,
        ]);

        $affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sem Percentual Proprio',
            'tracking_code' => 'DEFAULTPCT',
            'commission_percentage' => null,
            'status' => 'active',
        ]);

        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'affiliate_id' => $affiliate->id,
            'codigo' => 'SALE-AFF-2',
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        event(new SalePaid($sale->uuid, $this->userId));

        $commission = AffiliateCommission::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('5.00', $commission->percentage_applied);
        $this->assertSame('5.00', $commission->amount);
    }

    #[Test]
    public function paying_a_sale_with_an_affiliate_but_no_percentage_configured_anywhere_generates_no_commission(): void
    {
        $affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sem Percentual Nenhum',
            'tracking_code' => 'SEMPCT',
            'commission_percentage' => null,
            'status' => 'active',
        ]);

        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'affiliate_id' => $affiliate->id,
            'codigo' => 'SALE-AFF-3',
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        event(new SalePaid($sale->uuid, $this->userId));

        $this->assertSame(0, AffiliateCommission::where('sale_id', $sale->id)->count());
    }

    #[Test]
    public function paying_a_sale_without_an_affiliate_generates_no_commission(): void
    {
        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-NOAFF',
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        event(new SalePaid($sale->uuid, $this->userId));

        $this->assertSame(0, AffiliateCommission::where('sale_id', $sale->id)->count());
    }
}
