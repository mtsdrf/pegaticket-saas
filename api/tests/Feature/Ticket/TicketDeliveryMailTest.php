<?php

namespace Tests\Feature\Ticket;

use App\Mail\TicketDeliveryMail;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class TicketDeliveryMailTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('ticket-mail@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('tickets', 'read');
        $this->grantPermission('tickets', 'resend');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function issueTicket(array $attendee = []): Ticket
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);

        $sale = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
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
    public function it_sends_ticket_delivery_mail_when_tickets_are_issued(): void
    {
        Mail::fake();

        $ticket = $this->issueTicket(['attendee_name' => 'Maria Souza']);

        Mail::assertSent(TicketDeliveryMail::class, function (TicketDeliveryMail $mail) use ($ticket) {
            return $mail->hasTo($ticket->saleItem->sale->finalCustomer->email)
                && $mail->mode === 'issued'
                && $mail->tickets->pluck('uuid')->contains($ticket->uuid)
                && str_contains($mail->trackingUrl, '/rastreio/' . $ticket->saleItem->sale->uuid);
        });
    }

    #[Test]
    public function it_sends_ticket_delivery_mail_when_a_ticket_is_resent(): void
    {
        Mail::fake();

        $ticket = $this->issueTicket(['attendee_name' => 'Joao Lima']);
        Mail::assertSentCount(1);

        $this->auth()->postJson('/api/v1/tickets/' . $ticket->uuid . '/resend')
            ->assertStatus(200);

        Mail::assertSent(TicketDeliveryMail::class, function (TicketDeliveryMail $mail) use ($ticket) {
            return $mail->hasTo($ticket->saleItem->sale->finalCustomer->email)
                && $mail->mode === 'resent'
                && $mail->tickets->count() === 1
                && $mail->tickets->first()?->uuid === $ticket->uuid;
        });
    }

    #[Test]
    public function it_stays_silent_when_the_buyer_has_no_email(): void
    {
        Mail::fake();

        $client = $this->createClient($this->tenant->id);
        $client->update(['email' => '']);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);

        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        Mail::assertNothingSent();
    }
}
