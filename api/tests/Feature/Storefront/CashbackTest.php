<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Storefront\CashbackEarning;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Services\Auth\CustomerJWTService;
use App\Services\Order\OrderService;
use App\Services\Tenant\TenantExecutionContext;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Cashback (roadmap Delivery, Fase 5) — crédito no pagamento
 * (OrderService::pay(), via CreditCashbackOnOrderPaid), reversão no
 * despagamento, resgate no checkout (Guard 5 de
 * StorefrontCheckoutService::checkout()) e o comando agendado
 * cashback:process (carência/expiração).
 */
class CashbackTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;
    use CreatesStorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);
    }

    private function authenticatedCustomer(string $email = 'cliente@cashback.test'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    /**
     * Staff autenticado escopado ao MESMO tenant já criado por
     * createTenantWithStorefrontPlan() (diferente de SetsUpTenantScopedUser,
     * que sempre cria um tenant próprio) — necessário porque
     * OrderPaid/OrderUnpaid exigem actorId não-nulo (Auth::id()), então
     * pay()/unpay() precisam rodar via HTTP autenticado, não por chamada
     * direta ao service.
     */
    private function authenticatedStaff(Tenant $tenant, string $email = 'staff@cashback.test'): string
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Staff Cashback',
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $baseToken = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->json('data')['access_token'];

        $role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Member',
            'slug' => 'member-' . Str::random(6),
            'is_active' => true,
        ]);

        TenantUser::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'tenant_role_id' => $role->id,
            'is_active' => true,
        ]);

        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group ' . Str::random(6),
            'slug' => 'rbac-' . Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $funcId = DB::table('functionalities')->where('slug', 'orders')->value('id');

        if (!$funcId) {
            $funcId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Pedidos',
                'slug' => 'orders',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $actionId = DB::table('actions')->where('key', 'pay')->value('id');

        if (!$actionId) {
            $actionId = DB::table('actions')->insertGetId([
                'key' => 'pay',
                'name' => 'Pagar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('group_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'functionality_id' => $funcId,
            'action_id' => $actionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->withHeader('Authorization', 'Bearer ' . $baseToken)
            ->postJson('/api/v1/auth/switch-tenant', ['tenant_uuid' => $tenant->uuid])
            ->json('data')['access_token'];
    }

    private function payOrder(Order $order, string $staffToken): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $staffToken)
            ->patchJson('/api/v1/orders/' . $order->uuid . '/pay', [])
            ->assertStatus(200);
    }

    private function unpayOrder(Order $order, string $staffToken): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $staffToken)
            ->patchJson('/api/v1/orders/' . $order->uuid . '/unpay')
            ->assertStatus(200);
    }

    private function createAddressTrio(): array
    {
        $estado = Estado::create(['name' => 'Estado ' . Str::random(6), 'uf' => $this->nextUf()]);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'name' => 'Cidade ' . Str::random(6)]);
        $bairro = Bairro::create(['cidade_id' => $cidade->id, 'name' => 'Bairro ' . Str::random(6)]);

        return [$estado, $cidade, $bairro];
    }

    private function checkoutPayload(string $productUuid, array $address, array $overrides = []): array
    {
        [$estado, $cidade, $bairro] = $address;

        return array_merge([
            'items' => [
                ['product_uuid' => $productUuid, 'quantity' => 2],
            ],
            'client_name' => 'Cliente Loja',
            'client_last_name' => 'Sobrenome',
            'client_phone' => '11999998888',
            'notes' => null,
            'estado_uuid' => $estado->uuid,
            'cidade_uuid' => $cidade->uuid,
            'bairro_uuid' => $bairro->uuid,
            'logradouro' => 'Rua da Loja, 100',
            'numero' => '100',
            'complemento' => null,
            'cep' => '01000-000',
        ], $overrides);
    }

    /** perm:{func},{action} também checa tenantPlanAllowsFunctionality($func) — pay()/unpay() via HTTP exigem 'orders' no plano. */
    private function grantFunctionalityToPlan(Tenant $tenant, string $slug): void
    {
        $funcId = DB::table('functionalities')->where('slug', $slug)->value('id');

        if (!$funcId) {
            $funcId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst($slug),
                'slug' => $slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $tenant->plan_id,
            'functionality_id' => $funcId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureCashback($tenant, array $overrides = []): void
    {
        $settings = app(TenantSettingsService::class)->getForTenant($tenant->id);
        $settings->update(array_merge([
            'cashback_enabled' => true,
            'cashback_percentage' => 10,
            'cashback_max_per_order' => null,
            'cashback_hold_days' => 0,
            'cashback_expiration_days' => 90,
            'cashback_redeem_max_percentage' => 100,
        ], $overrides));
    }

    /** Loja com plano permitindo storefront+cashback, aberta 24h, produto R$10, sem taxa de entrega. */
    private function setupStore(array $cashbackOverrides = [], bool $allowsCashback = true): array
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->grantFunctionalityToPlan($tenant, 'orders');

        if ($allowsCashback) {
            $this->grantCashbackFunctionality($tenant);
        }

        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2], 0.0);
        $this->configureCashback($tenant, $cashbackOverrides);

        return [$tenant, $product, $address];
    }

    // --- Crédito ao pagar ---

    #[Test]
    public function paying_a_storefront_order_credits_cashback_available_immediately_when_no_hold(): void
    {
        [$tenant, $product, $address] = $this->setupStore(['cashback_hold_days' => 0]);
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', $this->checkoutPayload($product->uuid, $address));

        $response->assertStatus(201);
        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // Subtotal 2x R$10 = R$20, sem entrega. 10% = R$2,00 de cashback.
        app()->instance('tenant_id', $tenant->id);
        app(OrderService::class)->approve($order);
        $this->payOrder($order, $this->authenticatedStaff($tenant));

        $this->assertDatabaseHas('cashback_earnings', [
            'order_id' => $order->id,
            'amount' => '2.00',
            'remaining_amount' => '2.00',
            'status' => 'available',
        ]);
    }

    #[Test]
    public function paying_credits_cashback_as_pending_when_hold_days_configured(): void
    {
        [$tenant, $product, $address] = $this->setupStore(['cashback_hold_days' => 7]);
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', $this->checkoutPayload($product->uuid, $address));

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        app()->instance('tenant_id', $tenant->id);
        app(OrderService::class)->approve($order);
        $this->payOrder($order, $this->authenticatedStaff($tenant));

        $earning = CashbackEarning::where('order_id', $order->id)->firstOrFail();

        $this->assertEquals('pending', $earning->status);
        $this->assertTrue($earning->available_at->gt(now()->addDays(6)));
    }

    #[Test]
    public function cashback_is_capped_by_max_per_order(): void
    {
        [$tenant, $product, $address] = $this->setupStore(['cashback_max_per_order' => 1]);
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', $this->checkoutPayload($product->uuid, $address));

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        app()->instance('tenant_id', $tenant->id);
        app(OrderService::class)->approve($order);
        $this->payOrder($order, $this->authenticatedStaff($tenant));

        // Sem o teto seria R$2,00 (10% de R$20) — capado em R$1,00.
        $this->assertDatabaseHas('cashback_earnings', [
            'order_id' => $order->id,
            'amount' => '1.00',
        ]);
    }

    #[Test]
    public function unpaying_reverses_the_earning_it_created(): void
    {
        [$tenant, $product, $address] = $this->setupStore();
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', $this->checkoutPayload($product->uuid, $address));

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        app()->instance('tenant_id', $tenant->id);
        app(OrderService::class)->approve($order);
        $staffToken = $this->authenticatedStaff($tenant);
        $this->payOrder($order, $staffToken);
        $this->unpayOrder($order, $staffToken);

        $this->assertDatabaseHas('cashback_earnings', [
            'order_id' => $order->id,
            'status' => 'reversed',
            'remaining_amount' => '0.00',
        ]);
    }

    #[Test]
    public function staff_orders_never_earn_cashback(): void
    {
        [$tenant, $product] = $this->setupStore();
        $client = $this->createClient($tenant->id);

        app()->instance('tenant_id', $tenant->id);

        $order = app(OrderService::class)->create(new \App\DTOs\Order\CreateOrderDTO(
            tenantId: $tenant->id,
            clientUuid: $client->uuid,
            stockLocationUuid: null,
            isInstallment: false,
            installmentsCount: null,
            notes: null,
            expectedDeliveryDate: null,
            markAsDelivered: false,
            markAsPaid: false,
            items: [['product_uuid' => $product->uuid, 'quantity' => 1]],
            reserveStock: false,
        ));

        $this->payOrder($order, $this->authenticatedStaff($tenant));

        $this->assertDatabaseMissing('cashback_earnings', ['order_id' => $order->id]);
    }

    #[Test]
    public function plan_without_cashback_functionality_never_credits(): void
    {
        [$tenant, $product, $address] = $this->setupStore([], allowsCashback: false);
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', $this->checkoutPayload($product->uuid, $address));

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        app()->instance('tenant_id', $tenant->id);
        app(OrderService::class)->approve($order);
        $this->payOrder($order, $this->authenticatedStaff($tenant));

        $this->assertDatabaseMissing('cashback_earnings', ['order_id' => $order->id]);
    }

    // --- Resgate no checkout ---

    #[Test]
    public function checkout_redeems_available_cashback_reducing_total(): void
    {
        [$tenant, $product, $address] = $this->setupStore(['cashback_redeem_max_percentage' => 100]);
        [$customer, $token] = $this->authenticatedCustomer();

        // Primeira compra: gera R$2,00 de cashback disponível.
        $first = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', $this->checkoutPayload($product->uuid, $address));
        $firstOrder = Order::where('uuid', $first->json('data.order.uuid'))->firstOrFail();
        app()->instance('tenant_id', $tenant->id);
        app(OrderService::class)->approve($firstOrder);
        $this->payOrder($firstOrder, $this->authenticatedStaff($tenant));

        // Segunda compra: usa o cashback disponível (R$2,00 < subtotal R$20).
        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['use_cashback' => true])
            );

        $second->assertStatus(201);
        $secondOrder = Order::where('uuid', $second->json('data.order.uuid'))->firstOrFail();

        $this->assertEquals(2.0, (float) $secondOrder->cashback_redeemed_amount);
        $this->assertEquals(18.0, (float) $secondOrder->total_amount);

        $this->assertDatabaseHas('cashback_redemptions', [
            'order_id' => $secondOrder->id,
            'amount' => '2.00',
        ]);
    }

    #[Test]
    public function checkout_redemption_drains_batches_fifo_by_expiration(): void
    {
        [$tenant, $product, $address] = $this->setupStore(['cashback_redeem_max_percentage' => 100]);
        [$customer, $token] = $this->authenticatedCustomer();

        $sourceOrder = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $this->createClient($tenant->id)->id,
            'stock_location_id' => $this->createLocation($tenant->id)->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
            'stock_reserved' => false,
        ]);

        // Lote A expira primeiro (R$3,00), lote B expira depois (R$10,00) —
        // resgate de R$5,00 deve drenar o A inteiro (R$3,00) + parte do B
        // (R$2,00), nessa ordem.
        $batchA = CashbackEarning::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'order_id' => $sourceOrder->id,
            'amount' => 3,
            'remaining_amount' => 3,
            'status' => 'available',
            'available_at' => now()->subDay(),
            'expires_at' => now()->addDays(5),
        ]);

        $batchB = CashbackEarning::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'order_id' => $sourceOrder->id,
            'amount' => 10,
            'remaining_amount' => 10,
            'status' => 'available',
            'available_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['use_cashback' => true])
            );

        $response->assertStatus(201);
        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // Subtotal R$20, teto 100% => pede R$20, saldo total é R$13 (3+10).
        $this->assertEquals(13.0, (float) $order->cashback_redeemed_amount);
        $this->assertEquals(7.0, (float) $order->total_amount);

        $this->assertEquals('0.00', $batchA->fresh()->remaining_amount);
        $this->assertEquals('0.00', $batchB->fresh()->remaining_amount);

        $this->assertDatabaseCount('cashback_redemptions', 2);
    }

    #[Test]
    public function redeem_max_percentage_caps_the_amount_used(): void
    {
        [$tenant, $product, $address] = $this->setupStore(['cashback_redeem_max_percentage' => 10]);
        [$customer, $token] = $this->authenticatedCustomer();

        $sourceOrder = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $this->createClient($tenant->id)->id,
            'stock_location_id' => $this->createLocation($tenant->id)->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
            'stock_reserved' => false,
        ]);

        CashbackEarning::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'order_id' => $sourceOrder->id,
            'amount' => 50,
            'remaining_amount' => 50,
            'status' => 'available',
            'available_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['use_cashback' => true])
            );

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // Subtotal R$20, teto 10% => só R$2,00 usados, mesmo com R$50 de saldo.
        $this->assertEquals(2.0, (float) $order->cashback_redeemed_amount);
    }

    #[Test]
    public function rejecting_an_order_releases_its_cashback_redemption(): void
    {
        [$tenant, $product, $address] = $this->setupStore();
        [$customer, $token] = $this->authenticatedCustomer();

        $sourceOrder = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $this->createClient($tenant->id)->id,
            'stock_location_id' => $this->createLocation($tenant->id)->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
            'stock_reserved' => false,
        ]);

        $earning = CashbackEarning::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'order_id' => $sourceOrder->id,
            'amount' => 5,
            'remaining_amount' => 5,
            'status' => 'available',
            'available_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['use_cashback' => true])
            );

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        $this->assertEquals('0.00', $earning->fresh()->remaining_amount);

        app(TenantExecutionContext::class)->run(
            $tenant,
            fn () => app(OrderService::class)->reject($order, 'Fora de estoque')
        );

        $this->assertEquals('5.00', $earning->fresh()->remaining_amount);
        $this->assertDatabaseMissing('cashback_redemptions', ['order_id' => $order->id]);
    }

    // --- Nome do programa (roadmap Loja) ---

    #[Test]
    public function balance_program_name_defaults_to_cashback_and_uses_custom_name_when_set(): void
    {
        [$tenant] = $this->setupStore();
        [$customer] = $this->authenticatedCustomer();

        $service = app(\App\Services\Storefront\CashbackService::class);

        $default = $service->getBalance($tenant->id, $customer->id);
        $this->assertSame('Cashback', $default['program_name']);

        $this->configureCashback($tenant, ['cashback_name' => 'eCash']);

        $custom = $service->getBalance($tenant->id, $customer->id);
        $this->assertSame('eCash', $custom['program_name']);
    }

    // --- Comando agendado ---

    #[Test]
    public function cashback_process_command_promotes_and_expires_batches(): void
    {
        [$tenant] = $this->setupStore();
        [$customer] = $this->authenticatedCustomer();

        $sourceOrder = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $this->createClient($tenant->id)->id,
            'stock_location_id' => $this->createLocation($tenant->id)->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
            'stock_reserved' => false,
        ]);

        $pending = CashbackEarning::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'order_id' => $sourceOrder->id,
            'amount' => 5,
            'remaining_amount' => 5,
            'status' => 'pending',
            'available_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
        ]);

        $expiring = CashbackEarning::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'order_id' => $sourceOrder->id,
            'amount' => 3,
            'remaining_amount' => 3,
            'status' => 'available',
            'available_at' => now()->subDays(90),
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('cashback:process');

        $this->assertEquals('available', $pending->fresh()->status);
        $this->assertEquals('expired', $expiring->fresh()->status);
        $this->assertEquals('0.00', $expiring->fresh()->remaining_amount);
    }
}
