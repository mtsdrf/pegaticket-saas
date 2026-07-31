<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductOption;
use App\Models\Product\ProductOptionGroup;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Storefront\OrderRating;
use App\Models\Storefront\ProductFavorite;
use App\Models\Storefront\ProductPromotion;
use App\Models\Tenant\TenantSettings;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Catálogo público da loja (Delivery Fase 1) — GET /loja/{slug} e
 * GET /loja/{slug}/produtos, 100% públicos (sem jwt/tenant/perm).
 */
class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;
    use CreatesStorefrontFixtures;

    #[Test]
    public function show_returns_tenant_info_when_plan_allows_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true, ['name' => 'Padaria Maskats']);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', $tenant->slug)
            ->assertJsonPath('data.name', 'Padaria Maskats')
            ->assertJsonPath('data.logo_url', null);
    }

    #[Test]
    public function show_returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);

        $this->getJson('/api/v1/loja/' . $tenant->slug)
            ->assertStatus(404);
    }

    #[Test]
    public function show_returns_404_for_nonexistent_slug(): void
    {
        $this->getJson('/api/v1/loja/nao-existe-' . Str::random(8))
            ->assertStatus(404);
    }

    #[Test]
    public function products_lists_only_available_products_and_never_exposes_stock_or_cost(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $available = $this->createProduct($tenant->id, [
            'name' => 'Queijo Disponível',
            'is_available' => true,
            'last_purchase_cost' => 12.5,
        ]);
        $this->createProduct($tenant->id, [
            'name' => 'Queijo Indisponível',
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $available->uuid)
            ->assertJsonPath('data.0.name', 'Queijo Disponível')
            ->assertJsonMissingPath('data.0.stock_quantity')
            ->assertJsonMissingPath('data.0.last_purchase_cost')
            ->assertJsonMissingPath('data.0.sku')
            ->assertJsonMissingPath('data.0.min_stock');
    }

    #[Test]
    public function products_ignores_external_attempt_to_disable_is_available_filter(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->createProduct($tenant->id, ['name' => 'Indisponível', 'is_available' => false]);

        // Mesmo tentando "desligar" o filtro via query string, o catálogo
        // público nunca deve devolver produto indisponível.
        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos?is_available=false');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function products_only_lists_products_from_the_requested_tenant(): void
    {
        $tenantA = $this->createTenantWithStorefrontPlan(true);
        $tenantB = $this->createTenantWithStorefrontPlan(true);
        $this->createProduct($tenantA->id, ['name' => 'Produto A']);
        $this->createProduct($tenantB->id, ['name' => 'Produto B']);

        $response = $this->getJson('/api/v1/loja/' . $tenantA->slug . '/produtos');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Produto A');
    }

    #[Test]
    public function products_expose_active_option_groups_and_available_options_only(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $product = $this->createProduct($tenant->id, ['name' => 'Hambúrguer com adicionais']);

        $group = ProductOptionGroup::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'name' => 'Adicionais',
            'description' => 'Escolha seus extras',
            'min_select' => 1,
            'max_select' => 2,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        ProductOption::create([
            'tenant_id' => $tenant->id,
            'product_option_group_id' => $group->id,
            'name' => 'Queijo extra',
            'price' => 4.50,
            'sort_order' => 1,
            'is_available' => true,
        ]);

        ProductOption::create([
            'tenant_id' => $tenant->id,
            'product_option_group_id' => $group->id,
            'name' => 'Opção oculta',
            'price' => 9.90,
            'sort_order' => 2,
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.option_groups.0.name', 'Adicionais')
            ->assertJsonPath('data.0.option_groups.0.min_select', 1)
            ->assertJsonPath('data.0.option_groups.0.options.0.name', 'Queijo extra')
            ->assertJsonCount(1, 'data.0.option_groups.0.options');
    }

    #[Test]
    public function products_returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos')
            ->assertStatus(404);
    }

    #[Test]
    public function show_exposes_contact_data_and_disabled_storefront_flag(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true, [
            'email' => 'contato@empresa.test',
            'phone' => '1133334444',
            'mobile_phone' => '11988887777',
            'whatsapp' => '11988887777',
            'instagram' => '@empresa',
            'facebook' => 'empresa.oficial',
        ]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'storefront_enabled' => false,
        ]);

        $this->getJson('/api/v1/loja/' . $tenant->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.storefront_enabled', false)
            ->assertJsonPath('data.email', 'contato@empresa.test')
            ->assertJsonPath('data.phone', '1133334444')
            ->assertJsonPath('data.mobile_phone', '11988887777')
            ->assertJsonPath('data.whatsapp', '11988887777')
            ->assertJsonPath('data.instagram', '@empresa')
            ->assertJsonPath('data.facebook', 'empresa.oficial');
    }

    #[Test]
    public function products_return_404_when_storefront_is_disabled_in_settings(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'storefront_enabled' => false,
        ]);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos')
            ->assertStatus(404);
    }

    /**
     * business_hours/estimated_preparation_minutes (Delivery Fase 2) —
     * GET /loja/{slug} passa a expor os 7 dias e o tempo estimado de
     * preparo, sem round-trip extra pro frontend.
     */
    #[Test]
    public function show_exposes_business_hours_and_estimated_preparation_minutes(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'estimated_preparation_minutes' => 45,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug);

        $response->assertStatus(200)
            ->assertJsonPath('data.estimated_preparation_minutes', 45)
            ->assertJsonCount(7, 'data.business_hours')
            ->assertJsonPath('data.business_hours.0.day_of_week', 0)
            ->assertJsonPath('data.business_hours.0.is_closed', false);
    }

    /**
     * Reforma da loja — GET /loja/{slug} passa a expor endereço formatado,
     * coordenadas (do geocoding, seteadas direto no fixture) e formas de
     * pagamento aceitas.
     */
    #[Test]
    public function show_exposes_address_coordinates_and_accepted_payment_methods(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'São Paulo',
            'uf' => 'SP',
            'is_active' => true,
        ]);
        $cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => 'Campinas',
            'is_active' => true,
        ]);
        $bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidade->id,
            'name' => 'Cambuí',
            'is_active' => true,
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'cep' => '13000-000',
            'is_active' => true,
            'lat' => -22.9,
            'lng' => -47.06,
            'geocode_status' => 'success',
            'geocoded_at' => now(),
        ]);

        $tenant->update(['endereco_id' => $endereco->id]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'accepted_payment_methods' => ['pix', 'cash'],
        ]);

        $this->getJson('/api/v1/loja/' . $tenant->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.address', 'Rua das Flores, 123 - Cambuí, Campinas')
            ->assertJsonPath('data.address_lat', -22.9)
            ->assertJsonPath('data.address_lng', -47.06)
            ->assertJsonPath('data.accepted_payment_methods', ['pix', 'cash']);
    }

    /**
     * Sem endereço/formas configuradas: address null, coordenadas null e
     * accepted_payment_methods [].
     */
    #[Test]
    public function show_returns_null_address_and_empty_payment_methods_when_not_configured(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $this->getJson('/api/v1/loja/' . $tenant->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.address_lat', null)
            ->assertJsonPath('data.address_lng', null)
            ->assertJsonPath('data.accepted_payment_methods', []);
    }

    #[Test]
    public function show_defaults_business_hours_to_closed_when_tenant_never_configured_it(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug);

        $response->assertStatus(200)
            ->assertJsonCount(7, 'data.business_hours')
            ->assertJsonPath('data.business_hours.0.is_closed', true)
            ->assertJsonPath('data.estimated_preparation_minutes', null);
    }

    /**
     * GET /loja/{slug}/taxa-entrega/{bairro_uuid} (Delivery Fase 2) —
     * consulta prévia de taxa, usada pelo frontend antes de confirmar o
     * checkout.
     */
    #[Test]
    public function delivery_fee_endpoint_returns_fee_when_configured(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $estado = Estado::create(['name' => 'Estado ' . Str::random(6), 'uf' => $this->nextUf()]);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'name' => 'Cidade ' . Str::random(6)]);
        $bairro = Bairro::create(['cidade_id' => $cidade->id, 'name' => 'Bairro ' . Str::random(6)]);

        $this->createDeliveryFeeForBairro($tenant->id, $bairro, 9.9);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/taxa-entrega/' . $bairro->uuid);

        $response->assertStatus(200)->assertJsonPath('data.fee', 9.9);
    }

    #[Test]
    public function delivery_fee_endpoint_returns_404_when_bairro_has_no_fee(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $estado = Estado::create(['name' => 'Estado ' . Str::random(6), 'uf' => $this->nextUf()]);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'name' => 'Cidade ' . Str::random(6)]);
        $bairro = Bairro::create(['cidade_id' => $cidade->id, 'name' => 'Bairro ' . Str::random(6)]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/taxa-entrega/' . $bairro->uuid);

        $response->assertStatus(404)->assertJsonPath('code', 'DELIVERY_AREA_NOT_SERVED');
    }

    /**
     * promo_price no catálogo (Delivery Fase 3) —
     * StorefrontCatalogService::paginateProducts() carrega as promoções
     * ativas do tenant de uma vez (keyed por product_id) e repassa pro
     * StorefrontProductResource, sem N+1.
     */
    #[Test]
    public function products_exposes_promo_price_when_product_has_active_promotion(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $product = $this->createProduct($tenant->id, ['name' => 'Com Promoção', 'price' => 20]);
        $this->createProduct($tenant->id, ['name' => 'Sem Promoção', 'price' => 10]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'promo_price' => 15,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $response->assertStatus(200)->assertJsonCount(2, 'data');

        $data = collect($response->json('data'))->keyBy('name');
        $this->assertEquals(15.0, $data['Com Promoção']['promo_price']);
        $this->assertNull($data['Sem Promoção']['promo_price']);
    }

    #[Test]
    public function products_ignores_inactive_or_out_of_window_promotion(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $inactive = $this->createProduct($tenant->id, ['name' => 'Promoção Inativa', 'price' => 20]);
        $expired = $this->createProduct($tenant->id, ['name' => 'Promoção Expirada', 'price' => 20]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $inactive->id,
            'promo_price' => 15,
            'is_active' => false,
        ]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $expired->id,
            'promo_price' => 15,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $data = collect($response->json('data'))->keyBy('name');
        $this->assertNull($data['Promoção Inativa']['promo_price']);
        $this->assertNull($data['Promoção Expirada']['promo_price']);
    }

    #[Test]
    public function products_on_promotion_filter_returns_only_products_with_active_promotion(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $promoted = $this->createProduct($tenant->id, ['name' => 'Promovido', 'price' => 20]);
        $this->createProduct($tenant->id, ['name' => 'Normal', 'price' => 10]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $promoted->id,
            'promo_price' => 15,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos?on_promotion=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Promovido');
    }

    /**
     * is_favorited (roadmap Delivery, Fase 4 — retenção): só calculado
     * quando o catálogo é acessado com customer.jwt.optional autenticado
     * (portal_customer() populado); visitante anônimo sempre recebe false.
     */
    #[Test]
    public function products_exposes_is_favorited_only_when_authenticated_as_portal_customer(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $product = $this->createProduct($tenant->id, ['name' => 'Favoritável']);

        $anonymous = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');
        $anonymous->assertStatus(200)->assertJsonPath('data.0.is_favorited', false);

        $customer = FinalCustomer::create(['email' => 'fav@test.com']);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        ProductFavorite::create([
            'final_customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $authenticated = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $authenticated->assertStatus(200)->assertJsonPath('data.0.is_favorited', true);
    }

    #[Test]
    public function products_ignores_invalid_customer_token_and_stays_public(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->createProduct($tenant->id);

        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $response->assertStatus(200)->assertJsonPath('data.0.is_favorited', false);
    }

    /**
     * average_rating/ratings_count (roadmap Delivery, Fase 4 — retenção):
     * agregado simples de order_ratings filtrado por tenant, exposto em
     * GET /loja/{slug}. null/0 quando o tenant ainda não tem avaliação.
     */
    #[Test]
    public function show_exposes_average_rating_and_ratings_count(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $this->getJson('/api/v1/loja/' . $tenant->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);

        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);
        $customer = FinalCustomer::create(['email' => 'rate@test.com']);

        $orderA = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);
        OrderRating::create([
            'tenant_id' => $tenant->id,
            'order_id' => $orderA->id,
            'final_customer_id' => $customer->id,
            'rating' => 5,
        ]);

        $orderB = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);
        OrderRating::create([
            'tenant_id' => $tenant->id,
            'order_id' => $orderB->id,
            'final_customer_id' => $customer->id,
            'rating' => 3,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug);

        $response->assertStatus(200)->assertJsonPath('data.ratings_count', 2);
        $this->assertEquals(4.0, $response->json('data.average_rating'));
    }

    /**
     * sort=price_asc/price_desc (vitrine estilo iFood) — usa o preço
     * EFETIVO (promoção ativa quando houver, senão preço base), nunca o
     * preço de tabela ignorando promoção.
     */
    #[Test]
    public function products_sort_by_price_uses_effective_price_including_active_promotion(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $expensive = $this->createProduct($tenant->id, ['name' => 'Caro', 'price' => 30]);
        $withPromo = $this->createProduct($tenant->id, ['name' => 'Com Promoção Barata', 'price' => 20]);
        $middle = $this->createProduct($tenant->id, ['name' => 'Meio Termo', 'price' => 10]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $withPromo->id,
            'promo_price' => 5,
        ]);

        $ascending = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos?sort=price_asc');
        $ascending->assertStatus(200);
        $this->assertEquals(
            ['Com Promoção Barata', 'Meio Termo', 'Caro'],
            collect($ascending->json('data'))->pluck('name')->all()
        );

        $descending = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos?sort=price_desc');
        $descending->assertStatus(200);
        $this->assertEquals(
            ['Caro', 'Meio Termo', 'Com Promoção Barata'],
            collect($descending->json('data'))->pluck('name')->all()
        );
    }

    /**
     * sort=best_selling — soma de order_items.quantity dos últimos 90 dias,
     * mesma base de filtro (tenant, não cancelado, não deletado) de
     * AnalyticsService::orderItemsQuery(). Produto nunca vendido entra por
     * último (COALESCE 0), sem quebrar a ordenação.
     */
    #[Test]
    public function products_sort_by_best_selling_orders_by_quantity_sold_recently(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);

        $topSeller = $this->createProduct($tenant->id, ['name' => 'Mais Vendido']);
        $mediumSeller = $this->createProduct($tenant->id, ['name' => 'Vendido Ok']);
        $neverSold = $this->createProduct($tenant->id, ['name' => 'Nunca Vendido']);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
        ]);

        OrderItem::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'product_id' => $topSeller->id,
            'quantity' => 10,
            'unit_price' => 10,
            'line_total' => 100,
        ]);

        OrderItem::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'product_id' => $mediumSeller->id,
            'quantity' => 3,
            'unit_price' => 10,
            'line_total' => 30,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos?sort=best_selling');

        $response->assertStatus(200);
        $this->assertEquals(
            ['Mais Vendido', 'Vendido Ok', 'Nunca Vendido'],
            collect($response->json('data'))->pluck('name')->all()
        );
    }

    /**
     * Selos do card (gap-analysis de catálogo, item 3) —
     * StorefrontCatalogService::resolveBadgeIds()/NEW_PRODUCT_DAYS(14).
     */
    #[Test]
    public function products_expose_new_badge_only_for_recently_created_products(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $recent = $this->createProduct($tenant->id, ['name' => 'Produto Novo']);
        $old = $this->createProduct($tenant->id, ['name' => 'Produto Antigo']);
        $old->forceFill(['created_at' => now()->subDays(30)])->save();

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $response->assertStatus(200);
        $data = collect($response->json('data'))->keyBy('name');

        $this->assertContains('new', $data['Produto Novo']['badges']);
        $this->assertNotContains('new', $data['Produto Antigo']['badges']);
    }

    #[Test]
    public function products_expose_best_selling_badge_for_top_sold_products(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);

        $topSeller = $this->createProduct($tenant->id, ['name' => 'Mais Vendido']);
        $neverSold = $this->createProduct($tenant->id, ['name' => 'Nunca Vendido']);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
        ]);

        OrderItem::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'product_id' => $topSeller->id,
            'quantity' => 10,
            'unit_price' => 10,
            'line_total' => 100,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $response->assertStatus(200);
        $data = collect($response->json('data'))->keyBy('name');

        $this->assertContains('best_selling', $data['Mais Vendido']['badges']);
        $this->assertNotContains('best_selling', $data['Nunca Vendido']['badges']);
    }

    #[Test]
    public function products_expose_low_stock_badge_when_available_quantity_is_low(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $location = $this->createLocation($tenant->id);

        $lowStock = $this->createProduct($tenant->id, ['name' => 'Quase Acabando']);
        $wellStocked = $this->createProduct($tenant->id, ['name' => 'Estoque Cheio']);
        $outOfStock = $this->createProduct($tenant->id, ['name' => 'Zerado']);

        \App\Models\Stock\StockBalance::create([
            'tenant_id' => $tenant->id,
            'product_id' => $lowStock->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
            'quantity_blocked' => 0,
        ]);

        \App\Models\Stock\StockBalance::create([
            'tenant_id' => $tenant->id,
            'product_id' => $wellStocked->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'quantity_blocked' => 0,
        ]);

        \App\Models\Stock\StockBalance::create([
            'tenant_id' => $tenant->id,
            'product_id' => $outOfStock->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'quantity_blocked' => 0,
        ]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos');

        $response->assertStatus(200);
        $data = collect($response->json('data'))->keyBy('name');

        $this->assertContains('low_stock', $data['Quase Acabando']['badges']);
        $this->assertNotContains('low_stock', $data['Estoque Cheio']['badges']);
        // Zerado (0 disponível): não é "últimas unidades" — é ruptura, um
        // conceito diferente (produto some do card por is_available, não
        // ganha selo de "acabando").
        $this->assertNotContains('low_stock', $data['Zerado']['badges']);
    }

    #[Test]
    public function products_ignores_unknown_sort_value_and_falls_back_to_default(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->createProduct($tenant->id, ['name' => 'Zebra']);
        $this->createProduct($tenant->id, ['name' => 'Abacaxi']);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/produtos?sort=algo-invalido');

        $response->assertStatus(200);
        $this->assertEquals(
            ['Abacaxi', 'Zebra'],
            collect($response->json('data'))->pluck('name')->all()
        );
    }

    /**
     * GET /loja/{slug}/categorias (vitrine estilo iFood) — só categorias com
     * pelo menos 1 produto disponível, ordenadas por priority.
     */
    #[Test]
    public function categories_lists_only_categories_with_available_product_ordered_by_priority(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $categoryLow = ProductCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Segunda',
            'priority' => 2,
            'is_active' => true,
        ]);
        $categoryHigh = ProductCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Primeira',
            'priority' => 1,
            'is_active' => true,
        ]);
        $categoryWithoutAvailableProduct = ProductCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sem Produto Disponível',
            'priority' => 0,
            'is_active' => true,
        ]);

        foreach ([$categoryLow, $categoryHigh, $categoryWithoutAvailableProduct] as $category) {
            $type = ProductType::create([
                'tenant_id' => $tenant->id,
                'product_category_id' => $category->id,
                'name' => 'Tipo ' . $category->name,
                'is_active' => true,
            ]);

            Product::create([
                'tenant_id' => $tenant->id,
                'product_type_id' => $type->id,
                'name' => 'Produto ' . $category->name,
                'price' => 10,
                'is_available' => $category->id !== $categoryWithoutAvailableProduct->id,
            ]);
        }

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/categorias');

        $response->assertStatus(200);
        $this->assertEquals(
            ['Primeira', 'Segunda'],
            collect($response->json('data'))->pluck('name')->all()
        );
    }

    #[Test]
    public function categories_returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/categorias')
            ->assertStatus(404);
    }
}
