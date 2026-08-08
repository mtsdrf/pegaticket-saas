<?php

namespace Tests\Feature\Event;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use App\Models\Tenant\TenantPagBankConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Gate do roadmap R2.3 (seção 9.3): um evento já publicado (até então só
 * com ingressos gratuitos) não pode ganhar um ingresso pago vendável, nem
 * um ingresso existente pode virar pago, sem o tenant ter elegibilidade
 * financeira — mesmo requisito de EventService::publish() (R2.1), agora
 * aplicado no momento de criar/editar o TicketType.
 */
class TicketTypePaidGateTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('ticket-type-paid-gate-user@test.com');
        $this->grantPermission('ticket_types', 'create');
        $this->grantPermission('ticket_types', 'update');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function createEvent(string $status): Event
    {
        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Categoria '.Str::random(6),
            'is_active' => true,
        ]);

        return Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_category_id' => $category->id,
            'name' => 'Evento '.Str::random(6),
            'slug' => 'evento-'.Str::random(10),
            'type' => 'ingresso',
            'starts_at' => now()->addDays(15),
            'ends_at' => now()->addDays(15)->addHours(5),
            'visibility' => 'public',
            'status' => $status,
        ]);
    }

    private function createEnabledPagBankConnection(): void
    {
        TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => TenantPagBankConnection::STATUS_ENABLED,
            'account_id' => 'ACCO_'.Str::random(10),
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);
    }

    private function createFreeTicketType(Event $event): TicketType
    {
        return TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Ingresso gratuito',
            'price' => 0,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);
    }

    #[Test]
    public function creating_a_paid_active_ticket_type_on_a_published_event_is_blocked_without_eligibility(): void
    {
        $event = $this->createEvent('publicado');

        $this->auth()
            ->postJson('/api/v1/ticket-types', [
                'event_uuid' => $event->uuid,
                'name' => 'Ingresso VIP',
                'price' => 150,
                'status' => 'ativo',
                'min_per_order' => 1,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('ticket_types', ['name' => 'Ingresso VIP']);
    }

    #[Test]
    public function creating_a_paid_active_ticket_type_on_a_published_event_is_allowed_with_eligibility(): void
    {
        $event = $this->createEvent('publicado');
        $this->createEnabledPagBankConnection();

        $this->auth()
            ->postJson('/api/v1/ticket-types', [
                'event_uuid' => $event->uuid,
                'name' => 'Ingresso VIP',
                'price' => 150,
                'status' => 'ativo',
                'min_per_order' => 1,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('ticket_types', ['name' => 'Ingresso VIP', 'status' => 'ativo']);
    }

    #[Test]
    public function creating_a_free_ticket_type_on_a_published_event_is_never_blocked(): void
    {
        $event = $this->createEvent('publicado');

        $this->auth()
            ->postJson('/api/v1/ticket-types', [
                'event_uuid' => $event->uuid,
                'name' => 'Ingresso gratuito',
                'price' => 0,
                'status' => 'ativo',
                'min_per_order' => 1,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function creating_a_paid_but_not_active_ticket_type_is_never_blocked(): void
    {
        $event = $this->createEvent('publicado');

        $this->auth()
            ->postJson('/api/v1/ticket-types', [
                'event_uuid' => $event->uuid,
                'name' => 'Ingresso rascunho pago',
                'price' => 150,
                'status' => 'rascunho',
                'min_per_order' => 1,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function creating_a_paid_active_ticket_type_on_a_draft_event_is_never_blocked(): void
    {
        $event = $this->createEvent('rascunho');

        $this->auth()
            ->postJson('/api/v1/ticket-types', [
                'event_uuid' => $event->uuid,
                'name' => 'Ingresso VIP rascunho',
                'price' => 150,
                'status' => 'ativo',
                'min_per_order' => 1,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function turning_a_free_ticket_type_into_paid_on_a_published_event_is_blocked_without_eligibility(): void
    {
        $event = $this->createEvent('publicado');
        $ticketType = $this->createFreeTicketType($event);

        $this->auth()
            ->putJson('/api/v1/ticket-types/'.$ticketType->uuid, [
                'price' => 80,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id, 'price' => '0.00']);
    }

    #[Test]
    public function turning_a_free_ticket_type_into_paid_on_a_published_event_is_allowed_with_eligibility(): void
    {
        $event = $this->createEvent('publicado');
        $ticketType = $this->createFreeTicketType($event);
        $this->createEnabledPagBankConnection();

        $this->auth()
            ->putJson('/api/v1/ticket-types/'.$ticketType->uuid, [
                'price' => 80,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id, 'price' => '80.00']);
    }

    #[Test]
    public function editing_an_unrelated_field_of_an_already_paid_ticket_type_is_not_blocked_by_this_gate(): void
    {
        // Ingresso já pago+ativo ANTES da checagem existir (ex.: criado
        // fora do fluxo normal, ou conexão desabilitada depois da
        // publicação) — edição que não envolve tornar o ingresso pago
        // (ele já era) não deve ser bloqueada por este gate.
        $event = $this->createEvent('publicado');

        $ticketType = TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Ingresso já pago',
            'price' => 100,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);

        $this->auth()
            ->putJson('/api/v1/ticket-types/'.$ticketType->uuid, [
                'name' => 'Ingresso já pago (renomeado)',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id, 'name' => 'Ingresso já pago (renomeado)']);
    }

    #[Test]
    public function toggling_status_to_ativo_on_a_paid_ticket_type_of_a_published_event_is_blocked_without_eligibility(): void
    {
        $event = $this->createEvent('publicado');

        $ticketType = TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Ingresso pausado pago',
            'price' => 100,
            'status' => 'pausado',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);

        $this->auth()
            ->patchJson('/api/v1/ticket-types/'.$ticketType->uuid.'/toggle-status')
            ->assertStatus(422);

        $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id, 'status' => 'pausado']);
    }
}
