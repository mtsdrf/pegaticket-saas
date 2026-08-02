<?php

namespace Tests\Feature\Report;

use App\Models\Event\EventSession;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class AnalyticsCheckinInsightsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('analytics-checkin@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('tickets', 'checkin');
        $this->grantPermission('analytics', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function issueTicketForType(string $ticketTypeUuid, array $attendee = []): Ticket
    {
        $client = $this->createClient($this->tenant->id);

        $sale = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $ticketTypeUuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        $saleId = Sale::where('uuid', $sale['uuid'])->value('id');
        $ticket = Ticket::whereHas('saleItem', fn ($query) => $query->where('sale_id', $saleId))->firstOrFail();

        if ($attendee !== []) {
            $ticket->fill($attendee)->save();
        }

        return $ticket->fresh();
    }

    #[Test]
    public function it_returns_checkin_insights_grouped_by_session_and_ticket_type(): void
    {
        $ticketType = $this->createProduct($this->tenant->id, ['price' => 25]);
        $session = EventSession::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $ticketType->event_id,
            'name' => 'Sessão Principal',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
            'status' => 'agendada',
        ]);
        $ticketType->event->update(['reentry_enabled' => true]);
        $ticketType->update(['event_session_id' => $session->id, 'name' => 'Pista']);

        $vipType = $this->createProduct($this->tenant->id, ['price' => 80, 'name' => 'VIP']);
        $vipType->update(['event_session_id' => $session->id]);

        $firstTicket = $this->issueTicketForType($ticketType->uuid, ['attendee_name' => 'Maria']);
        $secondTicket = $this->issueTicketForType($ticketType->uuid, ['attendee_name' => 'Joao']);
        $thirdTicket = $this->issueTicketForType($vipType->uuid, ['attendee_name' => 'Ana']);

        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $firstTicket->qr_token])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'valido');

        $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $firstTicket->qr_token,
            'allow_reentry' => true,
            'reason' => 'Retorno liberado',
        ])->assertStatus(200)->assertJsonPath('data.result', 'reentrada_autorizada');

        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $secondTicket->qr_token])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'valido');

        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $secondTicket->qr_token])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'ja_utilizado');

        $this->auth()->postJson('/api/v1/tickets/checkin', ['qr_token' => $thirdTicket->qr_token])
            ->assertStatus(200)
            ->assertJsonPath('data.result', 'valido');

        $thirdTicket->checkins()->update(['checked_in_at' => now()->subYears(2)]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/checkin-insights?' . http_build_query([
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.total_reads', 4)
            ->assertJsonPath('data.totals.granted_reads', 3)
            ->assertJsonPath('data.totals.warning_reads', 1)
            ->assertJsonPath('data.totals.blocked_reads', 0)
            ->assertJsonPath('data.totals.reentries', 1)
            ->assertJsonPath('data.totals.unique_granted_tickets', 2)
            ->assertJsonPath('data.totals.attendance_rate', 66.67)
            ->assertJsonPath('data.by_session.0.session_name', 'Sessão Principal')
            ->assertJsonPath('data.by_session.0.total_reads', 4)
            ->assertJsonPath('data.by_session.0.attendance_rate', 66.67)
            ->assertJsonPath('data.by_ticket_type.0.ticket_type_name', 'Pista')
            ->assertJsonPath('data.by_ticket_type.0.total_reads', 4)
            ->assertJsonPath('data.by_ticket_type.0.attendance_rate', 100);
    }
}
