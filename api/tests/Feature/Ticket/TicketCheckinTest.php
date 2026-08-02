<?php

namespace Tests\Feature\Ticket;

use App\Models\Event\EventSession;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Controle de acesso/check-in (spec 5.16). POST /tickets/checkin.
 */
class TicketCheckinTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('ticket-checkin-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('tickets', 'checkin');
        $this->grantPermission('tickets', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function issueTicket(array $attendee = []): Ticket
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $ticket = Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $saleId))->firstOrFail();

        if (!empty($attendee)) {
            $ticket->fill($attendee)->save();
        }

        return $ticket->fresh();
    }

    #[Test]
    public function checkin_by_qr_token_marks_the_ticket_as_used_and_grants_entry(): void
    {
        $ticket = $this->issueTicket();

        $response = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'gate_name' => 'Portão A',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.result', 'valido')
            ->assertJsonPath('data.ticket.status', 'utilizado');

        $this->assertSame('utilizado', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_checkins', [
            'ticket_id' => $ticket->id,
            'result' => 'valido',
            'access_type' => 'entrada',
            'gate_name' => 'Portão A',
        ]);
    }

    #[Test]
    public function second_checkin_of_the_same_ticket_returns_ja_utilizado_without_reverting_status(): void
    {
        $ticket = $this->issueTicket();

        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $ticket->qr_token])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'valido');

        $second = $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $ticket->qr_token]);

        $second->assertStatus(200)->assertJsonPath('data.result', 'ja_utilizado');

        $this->assertSame('utilizado', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_checkins', [
            'ticket_id' => $ticket->id,
            'result' => 'ja_utilizado',
            'access_type' => 'tentativa',
            'reason' => 'ticket_ja_utilizado',
        ]);
        // Cada tentativa gera uma linha de histórico — 2 registros, 1 ticket.
        $this->assertSame(2, TicketCheckin::where('ticket_id', $ticket->id)->count());
    }

    #[Test]
    public function operator_can_authorize_reentry_for_a_used_ticket_and_log_the_reason(): void
    {
        $ticket = $this->issueTicket();
        $ticket->ticketType->event->update([
            'reentry_enabled' => true,
            'max_reentries' => 1,
        ]);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'gate_name' => 'Portão A',
        ])->assertStatus(200)->assertJsonPath('data.result', 'valido');

        $reentry = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'allow_reentry' => true,
            'reason' => 'Cliente saiu para fumar',
            'gate_name' => 'Portão B',
        ]);

        $reentry->assertStatus(200)
            ->assertJsonPath('data.result', 'reentrada_autorizada')
            ->assertJsonPath('data.checkin.access_type', 'reentrada')
            ->assertJsonPath('data.checkin.reason', 'Cliente saiu para fumar');

        $this->assertSame('utilizado', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_checkins', [
            'ticket_id' => $ticket->id,
            'result' => 'reentrada_autorizada',
            'access_type' => 'reentrada',
            'reason' => 'Cliente saiu para fumar',
            'gate_name' => 'Portão B',
        ]);
    }

    #[Test]
    public function reentry_is_rejected_when_the_event_and_ticket_type_do_not_allow_it(): void
    {
        $ticket = $this->issueTicket();

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
        ])->assertStatus(200)->assertJsonPath('data.result', 'valido');

        $response = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'allow_reentry' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.result', 'reentrada_nao_permitida')
            ->assertJsonPath('data.checkin.access_type', 'tentativa')
            ->assertJsonPath('data.checkin.reason', 'reentry_disabled');
    }

    #[Test]
    public function reentry_limit_is_enforced_after_the_configured_number_of_authorized_reentries(): void
    {
        $ticket = $this->issueTicket();
        $ticket->ticketType->event->update([
            'reentry_enabled' => true,
            'max_reentries' => 1,
        ]);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
        ])->assertStatus(200);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'allow_reentry' => true,
        ])->assertStatus(200)->assertJsonPath('data.result', 'reentrada_autorizada');

        $blocked = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'allow_reentry' => true,
        ]);

        $blocked->assertStatus(200)
            ->assertJsonPath('data.result', 'reentrada_limite_excedido')
            ->assertJsonPath('data.checkin.reason', 'reentry_limit_exceeded');
    }

    #[Test]
    public function reentry_cooldown_is_enforced_until_the_configured_interval_passes(): void
    {
        $ticket = $this->issueTicket();
        $ticket->ticketType->event->update([
            'reentry_enabled' => true,
            'reentry_cooldown_minutes' => 60,
        ]);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
        ])->assertStatus(200);

        $blocked = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'allow_reentry' => true,
        ]);

        $blocked->assertStatus(200)
            ->assertJsonPath('data.result', 'reentrada_intervalo_nao_atingido')
            ->assertJsonPath('data.checkin.reason', 'reentry_cooldown_active');

        TicketCheckin::where('ticket_id', $ticket->id)
            ->where('result', 'valido')
            ->update(['checked_in_at' => now()->subHours(2)]);

        $allowed = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'allow_reentry' => true,
        ]);

        $allowed->assertStatus(200)
            ->assertJsonPath('data.result', 'reentrada_autorizada')
            ->assertJsonPath('data.checkin.access_type', 'reentrada');
    }

    #[Test]
    public function ticket_type_reentry_rule_overrides_the_event_default(): void
    {
        $ticket = $this->issueTicket();
        $ticket->ticketType->event->update([
            'reentry_enabled' => false,
        ]);
        $ticket->ticketType->update([
            'reentry_enabled' => true,
            'max_reentries' => 1,
        ]);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
        ])->assertStatus(200);

        $response = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'allow_reentry' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.result', 'reentrada_autorizada')
            ->assertJsonPath('data.checkin.reason', null);
    }

    #[Test]
    public function checkin_history_lists_attempts_in_reverse_chronological_order_with_operator_data(): void
    {
        $ticket = $this->issueTicket();
        $operator = User::findOrFail($this->userId);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'gate_name' => 'Portão A',
            'device_info' => 'scanner-1',
        ])->assertStatus(200);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->qr_token,
            'gate_name' => 'Portão B',
            'device_info' => 'scanner-2',
        ])->assertStatus(200);

        $response = $this->auth()->getJson('/api/v1/tickets/' . $ticket->uuid . '/checkins');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.result', 'ja_utilizado')
            ->assertJsonPath('data.0.access_type', 'tentativa')
            ->assertJsonPath('data.0.reason', 'ticket_ja_utilizado')
            ->assertJsonPath('data.0.gate_name', 'Portão B')
            ->assertJsonPath('data.0.device_info', 'scanner-2')
            ->assertJsonPath('data.0.operator.uuid', $operator->uuid)
            ->assertJsonPath('data.1.result', 'valido')
            ->assertJsonPath('data.1.access_type', 'entrada')
            ->assertJsonPath('data.1.gate_name', 'Portão A')
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function checkin_summary_returns_shared_counters_and_recent_entries_filtered_by_context(): void
    {
        $sharedType = $this->createProduct($this->tenant->id, ['price' => 25]);
        $sharedEvent = $sharedType->event;
        $sharedSession = EventSession::create([
            'uuid' => (string) fake()->uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $sharedEvent->id,
            'name' => 'Sessão Principal',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'status' => 'agendada',
        ]);
        $sharedType->update(['event_session_id' => $sharedSession->id]);

        $firstClient = $this->createClient($this->tenant->id);
        $firstSale = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $firstClient->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $sharedType->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');
        $firstSaleId = Sale::where('uuid', $firstSale['uuid'])->value('id');
        $firstTicket = Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $firstSaleId))->firstOrFail();
        $firstTicket->update(['attendee_name' => 'Maria Souza']);

        $secondClient = $this->createClient($this->tenant->id);
        $secondSale = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $secondClient->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $sharedType->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');
        $secondSaleId = Sale::where('uuid', $secondSale['uuid'])->value('id');
        $secondTicket = Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $secondSaleId))->firstOrFail();
        $secondTicket->update(['attendee_name' => 'Joao Lima']);

        $otherType = $this->createProduct($this->tenant->id, ['price' => 30]);
        $otherEvent = $otherType->event;
        $otherSession = EventSession::create([
            'uuid' => (string) fake()->uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $otherEvent->id,
            'name' => 'Madrugada',
            'starts_at' => now()->addDays(15),
            'ends_at' => now()->addDays(15)->addHours(4),
            'status' => 'agendada',
        ]);
        $otherType->update(['event_session_id' => $otherSession->id]);
        $otherClient = $this->createClient($this->tenant->id);
        $otherSale = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $otherClient->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $otherType->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');
        $otherSaleId = Sale::where('uuid', $otherSale['uuid'])->value('id');
        $thirdTicket = Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $otherSaleId))->firstOrFail();

        $sharedEvent->update(['reentry_enabled' => true]);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $firstTicket->qr_token,
            'event_uuid' => $sharedEvent->uuid,
            'event_session_uuid' => $sharedSession->uuid,
            'gate_name' => 'Portão Norte',
        ])->assertStatus(200)->assertJsonPath('data.result', 'valido');

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $firstTicket->qr_token,
            'allow_reentry' => true,
            'reason' => 'Retorno liberado',
            'event_uuid' => $sharedEvent->uuid,
            'event_session_uuid' => $sharedSession->uuid,
            'gate_name' => 'Portão Norte',
        ])->assertStatus(200)->assertJsonPath('data.result', 'reentrada_autorizada');

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $secondTicket->qr_token,
            'event_uuid' => $sharedEvent->uuid,
            'event_session_uuid' => $sharedSession->uuid,
            'gate_name' => 'Portão Norte',
        ])->assertStatus(200)->assertJsonPath('data.result', 'valido');

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $secondTicket->qr_token,
            'event_uuid' => $sharedEvent->uuid,
            'event_session_uuid' => $sharedSession->uuid,
            'gate_name' => 'Portão Norte',
        ])->assertStatus(200)->assertJsonPath('data.result', 'ja_utilizado');

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $thirdTicket->qr_token,
            'event_uuid' => $otherEvent->uuid,
            'event_session_uuid' => $otherSession->uuid,
            'gate_name' => 'Portão Sul',
        ])->assertStatus(200)->assertJsonPath('data.result', 'valido');

        $summary = $this->auth()->getJson('/api/v1/tickets/checkin/summary?' . http_build_query([
            'event_uuid' => $sharedEvent->uuid,
            'event_session_uuid' => $sharedSession->uuid,
            'gate_name' => 'Portão Norte',
            'limit' => 3,
        ]));

        $summary->assertStatus(200)
            ->assertJsonPath('data.counters.total', 4)
            ->assertJsonPath('data.counters.granted', 3)
            ->assertJsonPath('data.counters.warning', 1)
            ->assertJsonPath('data.counters.blocked', 0)
            ->assertJsonPath('data.recent.0.gate_name', 'Portão Norte')
            ->assertJsonPath('data.recent.0.ticket.attendee_name', 'Joao Lima')
            ->assertJsonCount(3, 'data.recent');

        $this->assertSame(
            ['ja_utilizado', 'valido', 'reentrada_autorizada'],
            array_column($summary->json('data.recent'), 'result')
        );
    }

    #[Test]
    public function checkin_of_a_cancelled_ticket_is_refused(): void
    {
        $ticket = $this->issueTicket();
        $ticket->update(['status' => 'cancelado']);

        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $ticket->qr_token])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'cancelado');

        $this->assertSame('cancelado', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_checkins', [
            'ticket_id' => $ticket->id,
            'result' => 'cancelado',
            'access_type' => 'tentativa',
            'reason' => 'status_cancelado',
        ]);
    }

    #[Test]
    public function checkin_by_unknown_qr_token_returns_nao_encontrado_without_persisting_a_checkin(): void
    {
        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => 'does-not-exist'])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'nao_encontrado')
            ->assertJsonPath('data.ticket', null);

        $this->assertSame(0, TicketCheckin::count());
    }

    #[Test]
    public function manual_search_by_attendee_name_and_document_finds_the_ticket(): void
    {
        $ticket = $this->issueTicket(['attendee_name' => 'Maria Silva', 'attendee_document' => '12345678900']);

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'attendee_name' => 'Maria',
            'attendee_document' => '12345678900',
        ])->assertStatus(200)->assertJsonPath('data.result', 'valido');

        $this->assertSame('utilizado', $ticket->fresh()->status);
    }

    #[Test]
    public function a_ticket_from_another_tenant_is_not_found(): void
    {
        $ticket = $this->issueTicket();

        // Segundo tenant/usuário, sem nenhuma relação com o primeiro.
        $otherUserId = $this->userId;
        $this->setUpTenantScopedUser('ticket-checkin-other-tenant@test.com');
        $this->grantPermission('tickets', 'checkin');

        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $ticket->qr_token])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'nao_encontrado');

        $this->assertSame('ativo', $ticket->fresh()->status);
        $this->assertNotSame($otherUserId, $this->userId);
    }

    #[Test]
    public function checkin_request_without_any_identifier_is_rejected(): void
    {
        $this->auth()->postJson('/api/v1/tickets/checkin', [])
            ->assertStatus(422);
    }
}
