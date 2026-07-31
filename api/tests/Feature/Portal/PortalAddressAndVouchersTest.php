<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\CouponRedemption;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * "Meus endereços" (GET/PUT /portal/addresses) e "Meus vouchers"
 * (GET /portal/coupon-redemptions) do portal do cliente final (roadmap
 * Loja). Guard de posse: só endereços/vouchers do cliente autenticado.
 */
class PortalAddressAndVouchersTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);
    }

    private function createTenant(?string $name = null): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name ?? 'Tenant ' . Str::random(6),
            'slug' => 'tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function authenticatedCustomer(string $email = 'cliente@enderecos.test'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function confirmLink(FinalCustomer $customer, Tenant $tenant, $client): void
    {
        FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'confirmed_at' => now(),
        ]);
    }

    #[Test]
    public function index_lists_addresses_of_confirmed_linked_stores_only(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant('Loja Vinculada');
        $client = $this->createClient($tenant->id);
        $this->confirmLink($customer, $tenant, $client);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/addresses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_uuid', $client->uuid)
            ->assertJsonPath('data.0.tenant_name', 'Loja Vinculada')
            ->assertJsonPath('data.0.endereco.logradouro', 'Rua Teste, 123');
    }

    #[Test]
    public function update_changes_the_address_of_an_owned_client(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $this->confirmLink($customer, $tenant, $client);

        // Novo bairro (cidade/estado próprios) pra trocar.
        $estado = Estado::create(['uuid' => (string) Str::uuid(), 'name' => 'Novo Estado', 'uf' => 'NE']);
        $cidade = Cidade::create(['uuid' => (string) Str::uuid(), 'estado_id' => $estado->id, 'name' => 'Nova Cidade']);
        $bairro = Bairro::create(['uuid' => (string) Str::uuid(), 'cidade_id' => $cidade->id, 'name' => 'Novo Bairro']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/portal/addresses/' . $client->uuid, [
                'logradouro' => 'Rua Nova, 999',
                'numero' => '999',
                'complemento' => 'Apto 1',
                'cep' => '02000-000',
                'bairro_uuid' => $bairro->uuid,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.logradouro', 'Rua Nova, 999')
            ->assertJsonPath('data.bairro_name', 'Novo Bairro')
            ->assertJsonPath('data.cidade_name', 'Nova Cidade');

        $endereco = $client->fresh()->endereco;
        $this->assertSame('Rua Nova, 999', $endereco->logradouro);
        $this->assertSame($bairro->id, $endereco->bairro_id);
        $this->assertSame($cidade->id, $endereco->cidade_id);
        $this->assertSame($estado->id, $endereco->estado_id);
    }

    #[Test]
    public function update_of_a_client_without_confirmed_link_returns_404(): void
    {
        [, $token] = $this->authenticatedCustomer();

        // Client de outra loja, SEM vínculo confirmado do cliente autenticado.
        $otherTenant = $this->createTenant();
        $otherClient = $this->createClient($otherTenant->id);

        $bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => Cidade::create([
                'uuid' => (string) Str::uuid(),
                'estado_id' => Estado::create(['uuid' => (string) Str::uuid(), 'name' => 'E', 'uf' => 'EE'])->id,
                'name' => 'C',
            ])->id,
            'name' => 'B',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/portal/addresses/' . $otherClient->uuid, [
                'logradouro' => 'Rua Invasora, 1',
                'bairro_uuid' => $bairro->uuid,
            ])
            ->assertStatus(404);
    }

    #[Test]
    public function addresses_require_authentication(): void
    {
        $this->getJson('/api/v1/portal/addresses')->assertStatus(401);
        $this->putJson('/api/v1/portal/addresses/' . Str::uuid(), [])->assertStatus(401);
    }

    #[Test]
    public function voucher_history_only_returns_the_authenticated_customers_redemptions(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();
        [$otherCustomer] = $this->authenticatedCustomer('outro@enderecos.test');

        $tenant = $this->createTenant('Loja do Cupom');
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => false,
            'is_delivered' => false,
        ]);

        $coupon = Coupon::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'code' => 'PROMO10',
            'type' => 'fixed',
            'value' => 10,
            'is_active' => true,
        ]);

        CouponRedemption::create([
            'tenant_id' => $tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $customer->id,
            'order_id' => $order->id,
            'redeemed_at' => now(),
        ]);

        // Voucher de OUTRO cliente — nunca deve aparecer.
        CouponRedemption::create([
            'tenant_id' => $tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $otherCustomer->id,
            'order_id' => $order->id,
            'redeemed_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/coupon-redemptions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.coupon_code', 'PROMO10')
            ->assertJsonPath('data.0.tenant_name', 'Loja do Cupom')
            ->assertJsonPath('data.0.order_uuid', $order->uuid);
    }

    #[Test]
    public function voucher_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/portal/coupon-redemptions')->assertStatus(401);
    }
}
