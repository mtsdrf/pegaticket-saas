<?php

namespace Tests\Feature\Storefront;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Location\Endereco;
use App\Models\Order\Order;
use App\Models\Storefront\EventFavorite;
use App\Models\Storefront\OrderRating;
use App\Models\Tenant\TenantSettings;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Catálogo público da loja — GET /loja/{slug}, /loja/{slug}/eventos,
 * /loja/{slug}/eventos/{eventSlug} e /loja/{slug}/categorias, 100%
 * públicos (sem jwt/tenant/perm). Migrado de Product para
 * Event+TicketType+EventProduct (roadmap PegaTicket seção 2.4/4A,
 * 2026-07-31) — ver SIMPLIFICAÇÃO DOCUMENTADA em StorefrontCatalogService:
 * badges (new/best_selling/low_stock), atacado por quantidade, promoção
 * por item complexa e ordenação por preço/mais vendido do catálogo antigo
 * de Product foram descartados de propósito e não têm equivalente aqui.
 */
class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;
    use CreatesStorefrontFixtures;

    protected function createEvent(int $tenantId, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'tenant_id' => $tenantId,
            'name' => 'Evento ' . Str::random(6),
            'slug' => 'evento-' . Str::random(8),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'visibility' => 'public',
            'status' => 'publicado',
        ], $overrides));
    }

    protected function createTicketType(Event $event, array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Inteira',
            'price' => 50,
            'status' => 'ativo',
        ], $overrides));
    }

    protected function createEventProduct(Event $event, array $overrides = []): EventProduct
    {
        return EventProduct::create(array_merge([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Estacionamento',
            'price' => 20,
            'status' => 'ativo',
        ], $overrides));
    }

    #[Test]
    public function show_returns_tenant_info_when_plan_allows_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true, ['name' => 'Padaria PegaTicket']);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', $tenant->slug)
            ->assertJsonPath('data.name', 'Padaria PegaTicket')
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
    public function events_lists_only_published_and_public_events(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $published = $this->createEvent($tenant->id, ['name' => 'Show Publicado']);
        $this->createEvent($tenant->id, ['name' => 'Rascunho', 'status' => 'rascunho']);
        $this->createEvent($tenant->id, ['name' => 'Privado', 'visibility' => 'private']);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $published->uuid)
            ->assertJsonPath('data.0.name', 'Show Publicado');
    }

    #[Test]
    public function events_ignores_external_attempt_to_bypass_status_and_visibility_filter(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->createEvent($tenant->id, ['name' => 'Rascunho', 'status' => 'rascunho']);

        // Mesmo tentando forjar status/visibility via query string, o
        // catálogo público nunca deve devolver evento fora de
        // status=publicado/visibility=public (não são filtros aceitos por
        // StorefrontController::events()).
        $response = $this->getJson(
            '/api/v1/loja/' . $tenant->slug . '/eventos?status=rascunho&visibility=private'
        );

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function events_only_lists_events_from_the_requested_tenant(): void
    {
        $tenantA = $this->createTenantWithStorefrontPlan(true);
        $tenantB = $this->createTenantWithStorefrontPlan(true);
        $this->createEvent($tenantA->id, ['name' => 'Evento A']);
        $this->createEvent($tenantB->id, ['name' => 'Evento B']);

        $response = $this->getJson('/api/v1/loja/' . $tenantA->slug . '/eventos');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Evento A');
    }

    #[Test]
    public function events_returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos')
            ->assertStatus(404);
    }

    #[Test]
    public function events_return_404_when_storefront_is_disabled_in_settings(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'storefront_enabled' => false,
        ]);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos')
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
     * is_favorited (roadmap Delivery, Fase 4 — retenção, migrado pra
     * EventFavorite): só calculado quando o catálogo é acessado com
     * customer.jwt.optional autenticado (portal_customer() populado).
     * Visitante anônimo nunca ganha o atributo — EventResource usa
     * $this->when(offsetExists(...)), então a chave nem aparece no JSON
     * (comportamento novo, diferente do ProductResource antigo que sempre
     * expunha `false`).
     */
    #[Test]
    public function events_exposes_is_favorited_only_when_authenticated_as_portal_customer(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->createEvent($tenant->id, ['name' => 'Favoritável']);

        $anonymous = $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos');
        $anonymous->assertStatus(200)->assertJsonMissingPath('data.0.is_favorited');

        $customer = FinalCustomer::create(['email' => 'fav@test.com']);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        EventFavorite::create([
            'final_customer_id' => $customer->id,
            'event_id' => $event->id,
        ]);

        $authenticated = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/loja/' . $tenant->slug . '/eventos');

        $authenticated->assertStatus(200)->assertJsonPath('data.0.is_favorited', true);
    }

    #[Test]
    public function events_ignores_invalid_customer_token_and_stays_public(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->createEvent($tenant->id);

        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/loja/' . $tenant->slug . '/eventos');

        $response->assertStatus(200)->assertJsonMissingPath('data.0.is_favorited');
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
            'final_customer_id' => $client->id,
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
            'final_customer_id' => $client->id,
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

    #[Test]
    public function events_are_ordered_by_starts_at_ascending(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $later = $this->createEvent($tenant->id, ['name' => 'Mais Tarde', 'starts_at' => now()->addDays(20), 'ends_at' => now()->addDays(20)->addHours(2)]);
        $sooner = $this->createEvent($tenant->id, ['name' => 'Mais Cedo', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2)]);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos');

        $response->assertStatus(200);
        $this->assertEquals(
            ['Mais Cedo', 'Mais Tarde'],
            collect($response->json('data'))->pluck('name')->all()
        );
    }

    #[Test]
    public function events_filter_by_category_uuid(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $categoryA = EventCategory::create(['tenant_id' => $tenant->id, 'name' => 'Shows', 'is_active' => true]);
        $categoryB = EventCategory::create(['tenant_id' => $tenant->id, 'name' => 'Teatro', 'is_active' => true]);

        $this->createEvent($tenant->id, ['name' => 'Evento Shows', 'event_category_id' => $categoryA->id]);
        $this->createEvent($tenant->id, ['name' => 'Evento Teatro', 'event_category_id' => $categoryB->id]);

        $response = $this->getJson(
            '/api/v1/loja/' . $tenant->slug . '/eventos?event_category_uuid=' . $categoryA->uuid
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Evento Shows');
    }

    /**
     * Detalhe público de um evento (NOVO — não existia equivalente no
     * catálogo de comércio), com ticket_types/event_products aninhados,
     * restritos ao que está status=ativo e não deletado, sem expor custo.
     */
    #[Test]
    public function event_show_returns_detail_with_active_ticket_types_and_event_products_nested(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->createEvent($tenant->id, ['name' => 'Show com Ingressos']);

        $activeTicket = $this->createTicketType($event, ['name' => 'Inteira', 'price' => 100, 'last_purchase_cost' => 30]);
        $this->createTicketType($event, ['name' => 'Esgotada', 'status' => 'inativo']);

        $activeProduct = $this->createEventProduct($event, ['name' => 'Estacionamento']);
        $this->createEventProduct($event, ['name' => 'Item Inativo', 'status' => 'inativo']);

        $response = $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos/' . $event->slug);

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $event->uuid)
            ->assertJsonCount(1, 'data.ticket_types')
            ->assertJsonPath('data.ticket_types.0.uuid', $activeTicket->uuid)
            ->assertJsonMissingPath('data.ticket_types.0.last_purchase_cost')
            ->assertJsonCount(1, 'data.event_products')
            ->assertJsonPath('data.event_products.0.uuid', $activeProduct->uuid);
    }

    #[Test]
    public function event_show_returns_404_for_draft_event(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->createEvent($tenant->id, ['status' => 'rascunho']);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos/' . $event->slug)
            ->assertStatus(404);
    }

    #[Test]
    public function event_show_returns_404_for_private_event(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->createEvent($tenant->id, ['visibility' => 'private']);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos/' . $event->slug)
            ->assertStatus(404);
    }

    #[Test]
    public function event_show_returns_404_for_event_of_another_tenant(): void
    {
        $tenantA = $this->createTenantWithStorefrontPlan(true);
        $tenantB = $this->createTenantWithStorefrontPlan(true);
        $eventB = $this->createEvent($tenantB->id, ['slug' => 'evento-de-b']);

        $this->getJson('/api/v1/loja/' . $tenantA->slug . '/eventos/' . $eventB->slug)
            ->assertStatus(404);
    }

    #[Test]
    public function event_show_returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);
        // Sem plano/gate, o slug do evento nem chega a ser resolvido —
        // qualquer valor de eventSlug basta pra provar o 404 do gate.
        $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos/qualquer-evento')
            ->assertStatus(404);
    }

    #[Test]
    public function event_show_returns_404_when_storefront_is_disabled_in_settings(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->createEvent($tenant->id);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'storefront_enabled' => false,
        ]);

        $this->getJson('/api/v1/loja/' . $tenant->slug . '/eventos/' . $event->slug)
            ->assertStatus(404);
    }

    /**
     * GET /loja/{slug}/categorias (vitrine) — só categorias com pelo menos
     * 1 evento publicado/público, ordenadas por priority e depois nome.
     */
    #[Test]
    public function categories_lists_only_categories_with_available_event_ordered_by_priority(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $categoryLow = EventCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Segunda',
            'priority' => 2,
            'is_active' => true,
        ]);
        $categoryHigh = EventCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Primeira',
            'priority' => 1,
            'is_active' => true,
        ]);
        $categoryWithoutPublishedEvent = EventCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sem Evento Publicado',
            'priority' => 0,
            'is_active' => true,
        ]);

        $this->createEvent($tenant->id, ['name' => 'Evento Segunda', 'event_category_id' => $categoryLow->id]);
        $this->createEvent($tenant->id, ['name' => 'Evento Primeira', 'event_category_id' => $categoryHigh->id]);
        $this->createEvent($tenant->id, [
            'name' => 'Rascunho',
            'event_category_id' => $categoryWithoutPublishedEvent->id,
            'status' => 'rascunho',
        ]);

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
