<?php

namespace Tests\Feature\Ticket;

use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
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
        // Cada tentativa gera uma linha de histórico — 2 registros, 1 ticket.
        $this->assertSame(2, TicketCheckin::where('ticket_id', $ticket->id)->count());
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
