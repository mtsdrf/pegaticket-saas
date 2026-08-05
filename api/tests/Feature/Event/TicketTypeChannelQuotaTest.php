<?php

namespace Tests\Feature\Event;

use App\Models\Event\Event;
use App\Models\Event\TicketTypeChannelQuota;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Cota de inventário por canal de venda (opt-in) — CRUD de
 * TicketTypeChannelQuota e o efeito no cálculo de disponibilidade em dois
 * pontos: SaleService::create() (canal staff/afiliado sem hold) e
 * StorefrontHoldService::createHold() (canal storefront/afiliado via
 * reserva).
 */
class TicketTypeChannelQuotaTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('channel-quota-user@test.com');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function authenticatedCustomer(string $email = 'comprador-quota@test.com'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    #[Test]
    public function channel_quota_crud_lifecycle_works_end_to_end(): void
    {
        $this->grantPermission('ticket_type_channel_quotas', 'read');
        $this->grantPermission('ticket_type_channel_quotas', 'create');
        $this->grantPermission('ticket_type_channel_quotas', 'update');
        $this->grantPermission('ticket_type_channel_quotas', 'delete');

        $ticketType = $this->createProduct($this->tenant->id);

        $store = $this->auth()->postJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas", [
            'channel' => 'staff',
            'quota' => 10,
        ]);

        $store->assertStatus(201)
            ->assertJsonPath('data.channel', 'staff')
            ->assertJsonPath('data.quota', 10)
            ->assertJsonPath('data.ticket_type.uuid', $ticketType->uuid);

        $quotaUuid = $store->json('data.uuid');

        $this->auth()->getJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $quotaUuid);

        $this->auth()->getJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas/{$quotaUuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $quotaUuid);

        // Duplicar canal para o mesmo tipo de ingresso é rejeitado (unique
        // ticket_type_id+channel).
        $this->auth()->postJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas", [
            'channel' => 'staff',
            'quota' => 5,
        ])->assertStatus(422);

        $update = $this->auth()->putJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas/{$quotaUuid}", [
            'quota' => 25,
        ]);

        $update->assertStatus(200)->assertJsonPath('data.quota', 25);

        $this->auth()->deleteJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas/{$quotaUuid}")
            ->assertStatus(204);

        $this->auth()->getJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->assertSoftDeleted('ticket_type_channel_quotas', ['uuid' => $quotaUuid]);
    }

    #[Test]
    public function a_channel_quota_from_another_tenant_cannot_be_shown_or_updated(): void
    {
        $this->grantPermission('ticket_type_channel_quotas', 'create');

        $ticketType = $this->createProduct($this->tenant->id);

        $quotaUuid = $this->auth()->postJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas", [
            'channel' => 'staff',
            'quota' => 10,
        ])->assertStatus(201)->json('data.uuid');

        $this->setUpTenantScopedUser('channel-quota-other-tenant@test.com');
        $this->grantPermission('ticket_type_channel_quotas', 'read');
        $this->grantPermission('ticket_type_channel_quotas', 'update');

        $this->auth()->getJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas/{$quotaUuid}")
            ->assertStatus(404);

        $this->auth()->putJson("/api/v1/ticket-types/{$ticketType->uuid}/channel-quotas/{$quotaUuid}", [
            'quota' => 99,
        ])->assertStatus(404);
    }

    #[Test]
    public function configured_channel_quota_limits_the_staff_channel_even_with_general_stock_available(): void
    {
        $this->grantPermission('sales', 'create');

        $ticketType = $this->createProduct($this->tenant->id, ['quantity_available' => 100, 'price' => 10]);

        TicketTypeChannelQuota::create([
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $ticketType->id,
            'channel' => Sale::CHANNEL_STAFF,
            'quota' => 2,
        ]);

        $client = $this->createClient($this->tenant->id);

        // Consome a cota inteira do canal staff (2 unidades) — estoque
        // geral (100) sobra à vontade.
        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201);

        // Cota do canal staff esgotada (2/2 vendidos) — nova venda, mesmo
        // pedindo só 1 unidade e com estoque geral disponível, é recusada.
        $blocked = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
        ]);

        $blocked->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_CHANNEL_QUOTA');
    }

    #[Test]
    public function channel_without_configured_quota_remains_limited_only_by_general_stock(): void
    {
        [, $customerToken] = $this->authenticatedCustomer();
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 15]);
        $event = Event::findOrFail($ticketType->event_id);

        // Nenhuma TicketTypeChannelQuota cadastrada para este TicketType em
        // nenhum canal — a reserva via loja pública (canal storefront)
        // segue limitada só pelo estoque geral (comportamento anterior,
        // 100% preservado).
        $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
                'session_token' => 'sess-'.Str::random(10),
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 5],
                ],
            ])->assertStatus(201);

        $overflow = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
                'session_token' => 'sess-'.Str::random(10),
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
            ]);

        $overflow->assertStatus(422);
    }

    #[Test]
    public function general_stock_exhausted_blocks_even_when_channel_quota_still_has_room(): void
    {
        $this->grantPermission('sales', 'create');

        $ticketType = $this->createProduct($this->tenant->id, ['quantity_available' => 2, 'price' => 10]);

        // Cota do canal staff generosa (50) — bem acima do estoque geral
        // (2). O gargalo real deve continuar sendo o estoque geral.
        TicketTypeChannelQuota::create([
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $ticketType->id,
            'channel' => Sale::CHANNEL_STAFF,
            'quota' => 50,
        ]);

        $client = $this->createClient($this->tenant->id);

        $blocked = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 3],
            ],
        ]);

        $blocked->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_CHANNEL_QUOTA');

        // Dentro do estoque geral (2) e da cota (50), a venda é aceita
        // normalmente.
        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201);
    }

    #[Test]
    public function channel_quotas_are_isolated_per_tenant_in_availability_calculation(): void
    {
        $this->grantPermission('sales', 'create');

        $ticketType = $this->createProduct($this->tenant->id, ['quantity_available' => 10, 'price' => 10]);

        $otherTenant = $this->createTenantWithStorefrontPlan(false);
        $otherTicketType = $this->createProduct($otherTenant->id, ['quantity_available' => 10, 'price' => 10]);

        // Cota apertada (1) só no ticket type do OUTRO tenant — não pode
        // vazar e afetar a disponibilidade do ticket type deste tenant.
        TicketTypeChannelQuota::create([
            'tenant_id' => $otherTenant->id,
            'ticket_type_id' => $otherTicketType->id,
            'channel' => Sale::CHANNEL_STAFF,
            'quota' => 1,
        ]);

        $client = $this->createClient($this->tenant->id);

        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 5],
            ],
        ])->assertStatus(201);
    }
}
