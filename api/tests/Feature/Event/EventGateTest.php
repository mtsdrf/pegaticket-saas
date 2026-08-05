<?php

namespace Tests\Feature\Event;

use App\Models\Event\Event;
use App\Models\Event\EventGate;
use App\Models\Event\TicketType;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * CRUD de EventGate ("portaria" formal, opcional) e a validação opt-in
 * de restrição de ticket_type no check-in (CheckinService).
 */
class EventGateTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('event-gate-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('tickets', 'checkin');
        $this->grantPermission('tickets', 'read');
        $this->grantPermission('event_gates', 'read');
        $this->grantPermission('event_gates', 'create');
        $this->grantPermission('event_gates', 'update');
        $this->grantPermission('event_gates', 'delete');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function issueTicketFor(TicketType $ticketType): Ticket
    {
        $client = $this->createClient($this->tenant->id);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');

        return Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $saleId))->firstOrFail();
    }

    #[Test]
    public function gate_crud_lifecycle_works_end_to_end(): void
    {
        $ticketType = $this->createProduct($this->tenant->id);
        $event = Event::findOrFail($ticketType->event_id);

        $store = $this->auth()->postJson("/api/v1/events/{$event->uuid}/gates", [
            'name' => 'Portão A',
        ]);

        $store->assertStatus(201)
            ->assertJsonPath('data.name', 'Portão A')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.event.uuid', $event->uuid)
            ->assertJsonPath('data.allowed_ticket_types', []);

        $gateUuid = $store->json('data.uuid');

        $this->auth()->getJson("/api/v1/events/{$event->uuid}/gates")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $gateUuid);

        $this->auth()->getJson("/api/v1/events/{$event->uuid}/gates/{$gateUuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $gateUuid);

        $update = $this->auth()->putJson("/api/v1/events/{$event->uuid}/gates/{$gateUuid}", [
            'name' => 'Portão A - Renomeado',
            'ticket_type_uuids' => [$ticketType->uuid],
        ]);

        $update->assertStatus(200)
            ->assertJsonPath('data.name', 'Portão A - Renomeado')
            ->assertJsonCount(1, 'data.allowed_ticket_types')
            ->assertJsonPath('data.allowed_ticket_types.0.uuid', $ticketType->uuid);

        $this->auth()->deleteJson("/api/v1/events/{$event->uuid}/gates/{$gateUuid}")
            ->assertStatus(204);

        $this->auth()->getJson("/api/v1/events/{$event->uuid}/gates")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->assertSoftDeleted('event_gates', ['uuid' => $gateUuid]);
    }

    #[Test]
    public function checkin_is_granted_through_a_gate_without_ticket_type_restriction(): void
    {
        $ticketType = $this->createProduct($this->tenant->id);
        $event = Event::findOrFail($ticketType->event_id);

        $this->auth()->postJson("/api/v1/events/{$event->uuid}/gates", [
            'name' => 'Portão Livre',
        ])->assertStatus(201);

        $ticket = $this->issueTicketFor($ticketType);

        $response = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'gate_name' => 'Portão Livre',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.result', 'valido');
        $this->assertSame('utilizado', $ticket->fresh()->status);
    }

    #[Test]
    public function checkin_is_refused_when_the_ticket_type_is_not_allowed_through_the_restricted_gate(): void
    {
        $allowedType = $this->createProduct($this->tenant->id, ['name' => 'Pista']);
        $event = Event::findOrFail($allowedType->event_id);
        $vipType = TicketType::create([
            'uuid' => (string) fake()->uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'name' => 'VIP',
            'price' => 50,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);

        $store = $this->auth()->postJson("/api/v1/events/{$event->uuid}/gates", [
            'name' => 'Portão VIP',
            'ticket_type_uuids' => [$vipType->uuid],
        ]);
        $store->assertStatus(201);

        $ticket = $this->issueTicketFor($allowedType);

        $response = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'gate_name' => 'Portão VIP',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.result', 'portaria_incorreta')
            ->assertJsonPath('data.checkin.access_type', 'tentativa')
            ->assertJsonPath('data.checkin.reason', 'portaria_incorreta');

        $this->assertSame('ativo', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_checkins', [
            'ticket_id' => $ticket->id,
            'result' => 'portaria_incorreta',
            'gate_name' => 'Portão VIP',
        ]);

        // Ingresso VIP, pelo próprio portão VIP, é liberado normalmente.
        $vipTicket = $this->issueTicketFor($vipType);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $vipTicket->qr_token,
            'gate_name' => 'Portão VIP',
        ])->assertStatus(200)->assertJsonPath('data.result', 'valido');
    }

    #[Test]
    public function checkin_behaves_as_before_when_the_event_has_no_registered_gates(): void
    {
        $ticketType = $this->createProduct($this->tenant->id);
        $ticket = $this->issueTicketFor($ticketType);

        // gate_name livre, sem nenhum EventGate cadastrado neste evento —
        // continua sem nenhuma validação (regressão zero).
        $response = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'gate_name' => 'Qualquer Portão Digitado',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.result', 'valido');
        $this->assertSame('utilizado', $ticket->fresh()->status);
        $this->assertSame(0, EventGate::count());
    }

    #[Test]
    public function a_gate_from_another_tenant_cannot_be_shown_or_updated(): void
    {
        $ticketType = $this->createProduct($this->tenant->id);
        $event = Event::findOrFail($ticketType->event_id);

        $gateUuid = $this->auth()->postJson("/api/v1/events/{$event->uuid}/gates", [
            'name' => 'Portão A',
        ])->assertStatus(201)->json('data.uuid');

        $this->setUpTenantScopedUser('event-gate-other-tenant@test.com');
        $this->grantPermission('event_gates', 'read');
        $this->grantPermission('event_gates', 'update');

        $this->auth()->getJson("/api/v1/events/{$event->uuid}/gates/{$gateUuid}")
            ->assertStatus(404);

        $this->auth()->putJson("/api/v1/events/{$event->uuid}/gates/{$gateUuid}", [
            'name' => 'Tentativa de invasão',
        ])->assertStatus(404);
    }

    #[Test]
    public function a_gate_from_another_event_in_the_same_tenant_cannot_be_shown_updated_or_deleted(): void
    {
        $ticketTypeA = $this->createProduct($this->tenant->id);
        $eventA = Event::findOrFail($ticketTypeA->event_id);

        $ticketTypeB = $this->createProduct($this->tenant->id);
        $eventB = Event::findOrFail($ticketTypeB->event_id);

        $gateUuid = $this->auth()->postJson("/api/v1/events/{$eventA->uuid}/gates", [
            'name' => 'Portão A',
        ])->assertStatus(201)->json('data.uuid');

        $this->auth()->getJson("/api/v1/events/{$eventB->uuid}/gates/{$gateUuid}")
            ->assertStatus(404);

        $this->auth()->putJson("/api/v1/events/{$eventB->uuid}/gates/{$gateUuid}", [
            'name' => 'Tentativa via evento errado',
        ])->assertStatus(404);

        $this->auth()->deleteJson("/api/v1/events/{$eventB->uuid}/gates/{$gateUuid}")
            ->assertStatus(404);

        $this->assertDatabaseHas('event_gates', ['uuid' => $gateUuid, 'name' => 'Portão A', 'deleted_at' => null]);
    }
}
