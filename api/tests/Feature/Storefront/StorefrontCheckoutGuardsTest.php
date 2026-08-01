<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Storefront\StoreBusinessHour;
use App\Models\Tenant\TenantSettings;
use App\Services\Auth\CustomerJWTService;
use App\Services\Storefront\StoreBusinessHoursService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Guards do checkout público: horário de funcionamento e pedido mínimo.
 * Ver App\Services\Storefront\StorefrontCheckoutService::checkout().
 */
class StorefrontCheckoutGuardsTest extends TestCase
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

    private function authenticatedCustomer(string $email = 'cliente@guards.test'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
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
                ['ticket_type_uuid' => $productUuid, 'quantity' => 2],
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

    private function closeStoreToday(int $tenantId): void
    {
        for ($day = 0; $day <= 6; $day++) {
            StoreBusinessHour::create([
                'tenant_id' => $tenantId,
                'day_of_week' => $day,
                'opens_at' => null,
                'closes_at' => null,
                'is_closed' => true,
            ]);
        }
    }

    #[Test]
    public function checkout_blocks_with_store_closed_when_no_business_hours_configured(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(422)->assertJsonPath('code', 'STORE_CLOSED');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_blocks_with_store_closed_when_explicitly_closed_today(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->closeStoreToday($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(422)->assertJsonPath('code', 'STORE_CLOSED');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_passes_when_store_is_open(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Regressão do bug conceitual real (2026-07-18): horário que atravessa
     * a meia-noite (abre 20:00, fecha 03:00) precisa contar como aberto
     * tanto na madrugada seguinte (horário de HOJE, mas ainda dentro da
     * janela de ONTEM) quanto logo depois de abrir à noite (horário de
     * HOJE, dentro da própria janela de hoje). Ver
     * StoreBusinessHoursService::isOpenNow().
     */
    private function setOvernightHoursEveryDay(int $tenantId): void
    {
        for ($day = 0; $day <= 6; $day++) {
            StoreBusinessHour::create([
                'tenant_id' => $tenantId,
                'day_of_week' => $day,
                'opens_at' => '20:00:00',
                'closes_at' => '03:00:00',
                'is_closed' => false,
            ]);
        }
    }

    #[Test]
    public function is_open_now_true_just_after_opening_overnight(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->setOvernightHoursEveryDay($tenant->id);

        Carbon::setTestNow(Carbon::parse('2026-07-18 21:00:00')); // sábado, 21h — acabou de abrir

        $this->assertTrue(app(StoreBusinessHoursService::class)->isOpenNow($tenant->id));
    }

    #[Test]
    public function is_open_now_true_after_midnight_still_within_overnight_window(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->setOvernightHoursEveryDay($tenant->id);

        Carbon::setTestNow(Carbon::parse('2026-07-19 01:30:00')); // domingo, 01h30 — madrugada de ontem

        $this->assertTrue(app(StoreBusinessHoursService::class)->isOpenNow($tenant->id));
    }

    #[Test]
    public function is_open_now_false_after_overnight_window_closes(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->setOvernightHoursEveryDay($tenant->id);

        Carbon::setTestNow(Carbon::parse('2026-07-19 10:00:00')); // domingo, 10h — já fechou (03h) e ainda não abriu de novo (20h)

        $this->assertFalse(app(StoreBusinessHoursService::class)->isOpenNow($tenant->id));
    }

    #[Test]
    public function checkout_passes_at_1am_with_overnight_hours_from_yesterday(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->setOvernightHoursEveryDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        // Congela o relógio ANTES de emitir o token — senão o JWT nasce com
        // iat/exp calculados no "agora" real do teste, e ao voltar o
        // relógio pra 01h30 (antes do "agora" real de quando o teste
        // rodou) ele já nasceria "expirado" na comparação.
        Carbon::setTestNow(Carbon::parse('2026-07-19 01:30:00'));
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);
    }

    #[Test]
    public function checkout_blocks_with_below_minimum_order_and_shows_missing_amount(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'minimum_order_value' => 30,
        ]);

        // 2 x R$10 = R$20 de subtotal, mínimo R$30 -> faltam R$10,00.
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(422)->assertJsonPath('code', 'BELOW_MINIMUM_ORDER');
        $this->assertStringContainsString('10,00', $response->json('message'));
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_passes_minimum_order_guard_when_subtotal_meets_it(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'minimum_order_value' => 20,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);
    }

    /**
     * Achado de code review: o guard de pedido mínimo somava sempre pelo
     * Product.price BASE — pra um cliente já vinculado (segunda compra+)
     * com desconto de categoria aplicável, isso podia LIBERAR um checkout
     * cujo total final (calculado por OrderService::create() via
     * ProductPricingService, já com o desconto) ficasse abaixo do mínimo
     * configurado. Preço base = 20 (2 x R$10, passa o mínimo de R$15);
     * preço com desconto de categoria = 10 (2 x R$5, fica ABAIXO do
     * mínimo) — corrigido pra usar o mesmo preço com desconto no guard
     * quando o Client já existe.
     */
    #[Test]
    public function checkout_repeat_customer_still_passes_minimum_without_category_discount(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [$customer, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
        ]);

        // Primeira compra: cria o FinalCustomerTenantLink (sem mínimo configurado ainda).
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            )->assertStatus(201);

        TenantSettings::where('tenant_id', $tenant->id)->update(['minimum_order_value' => 15]);

        // Segunda compra, mesmo carrinho (2 unidades): base = 20, então o
        // pedido continua acima do mínimo configurado.
        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $second->assertStatus(201);
    }

    #[Test]
    public function checkout_blocks_with_delivery_area_not_served_when_bairro_has_no_fee(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();

        // Nenhuma configuração legada de entrega cadastrada para este bairro.
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(422)->assertJsonPath('code', 'DELIVERY_AREA_NOT_SERVED');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_sums_delivery_fee_into_total_amount_and_persists_it_separately(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2], 7.5);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // 2 x R$10 (produto) + R$7,50 (entrega) = R$27,50.
        $this->assertEquals(27.5, (float) $order->total_amount);
        $this->assertEquals(7.5, (float) $order->delivery_fee);
    }
}
