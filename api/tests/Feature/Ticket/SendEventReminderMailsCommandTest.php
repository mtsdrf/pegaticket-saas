<?php

namespace Tests\Feature\Ticket;

use App\Mail\TicketDeliveryMail;
use App\Models\Event\Event;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class SendEventReminderMailsCommandTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('event-reminder@test.com');
        $this->grantPermission('sales', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function issuePaidSale(): Sale
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 25]);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        return Sale::where('uuid', $response['uuid'])->firstOrFail();
    }

    #[Test]
    public function sends_a_reminder_for_a_paid_sale_whose_event_starts_within_the_window(): void
    {
        Mail::fake();

        $sale = $this->issuePaidSale();
        $eventId = Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $sale->id))
            ->firstOrFail()->saleItem->ticketType->event_id;
        Event::whereKey($eventId)->update(['starts_at' => now()->addHours(10)]);

        $this->artisan('sales:send-event-reminders --hours-ahead=24')->assertExitCode(0);

        Mail::assertSent(TicketDeliveryMail::class, function (TicketDeliveryMail $mail) use ($sale) {
            return $mail->mode === 'reminder' && $mail->sale->uuid === $sale->uuid;
        });

        $this->assertNotNull(Sale::whereKey($sale->id)->value('reminder_sent_at'));
    }

    private function countRemindersSent(): int
    {
        return collect(Mail::sent(TicketDeliveryMail::class))
            ->filter(fn (TicketDeliveryMail $mail) => $mail->mode === 'reminder')
            ->count();
    }

    #[Test]
    public function does_not_remind_twice_for_the_same_sale(): void
    {
        Mail::fake();

        $sale = $this->issuePaidSale();
        $eventId = Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $sale->id))
            ->firstOrFail()->saleItem->ticketType->event_id;
        Event::whereKey($eventId)->update(['starts_at' => now()->addHours(10)]);

        $this->artisan('sales:send-event-reminders --hours-ahead=24');
        $this->artisan('sales:send-event-reminders --hours-ahead=24');

        $this->assertSame(1, $this->countRemindersSent());
    }

    #[Test]
    public function does_not_remind_when_the_event_is_outside_the_window(): void
    {
        Mail::fake();

        $this->issuePaidSale();

        $this->artisan('sales:send-event-reminders --hours-ahead=24');

        $this->assertSame(0, $this->countRemindersSent());
    }
}
