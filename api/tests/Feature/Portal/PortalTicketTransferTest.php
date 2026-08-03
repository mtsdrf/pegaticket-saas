<?php

namespace Tests\Feature\Portal;

use App\Mail\TicketDeliveryMail;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use App\Models\Ticket\Ticket;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * POST /portal/tickets/{uuid}/transfer — "titularidade e transferência"
 * (roadmap Fase 4). Posse via PortalCustomerService::findOwnedTicket(),
 * mesmo caminho de findOwnedOrder() usado pelo resto do Portal.
 */
class PortalTicketTransferTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant '.Str::random(6),
            'slug' => 'tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function authenticatedCustomer(string $email = 'cliente@test.com'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function createOwnedTicket(FinalCustomer $customer, string $token, array $ticketOverrides = []): array
    {
        $tenant = $this->createTenant();
        $ticketType = $this->createProduct($tenant->id, ['price' => 40]);

        $order = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'is_installment' => false,
            'total_amount' => 40,
            'is_paid' => true,
            'is_completed' => false,
        ]);

        $item = SaleItem::create([
            'tenant_id' => $tenant->id,
            'sale_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 40,
            'line_total' => 40,
        ]);

        $ticket = Ticket::create(array_merge([
            'tenant_id' => $tenant->id,
            'sale_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => 'Comprador Original',
            'status' => 'ativo',
            'issued_at' => now(),
        ], $ticketOverrides));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $order->uuid])
            ->assertStatus(200);

        return [$ticket, $order];
    }

    #[Test]
    public function transfers_an_active_ticket_and_rotates_its_access_credentials(): void
    {
        Mail::fake();
        [$customer, $token] = $this->authenticatedCustomer();
        [$ticket] = $this->createOwnedTicket($customer, $token);

        $originalCode = $ticket->code;
        $originalQrToken = $ticket->qr_token;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/transfer", [
                'attendee_name' => 'Novo Participante',
                'attendee_document' => '12345678900',
            ]);

        $response->assertStatus(200);
        $this->assertSame('Novo Participante', $response->json('data.attendee_name'));

        $ticket->refresh();
        $this->assertSame('Novo Participante', $ticket->attendee_name);
        $this->assertSame('12345678900', $ticket->attendee_document);
        $this->assertNotSame($originalCode, $ticket->code);
        $this->assertNotSame($originalQrToken, $ticket->qr_token);
        $this->assertSame('ativo', $ticket->status);

        Mail::assertSent(TicketDeliveryMail::class, fn ($mail) => $mail->mode === 'transferred');
    }

    #[Test]
    public function rejects_transferring_a_ticket_that_was_already_used(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();
        [$ticket] = $this->createOwnedTicket($customer, $token, ['status' => 'utilizado']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/transfer", [
                'attendee_name' => 'Novo Participante',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_TICKET_STATE');

        $ticket->refresh();
        $this->assertSame('Comprador Original', $ticket->attendee_name);
    }

    #[Test]
    public function cannot_transfer_a_ticket_belonging_to_another_customer(): void
    {
        [, $token] = $this->authenticatedCustomer('a@test.com');
        [$otherCustomer, $otherToken] = $this->authenticatedCustomer('b@test.com');
        [$ticket] = $this->createOwnedTicket($otherCustomer, $otherToken);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/transfer", [
                'attendee_name' => 'Novo Participante',
            ])
            ->assertStatus(404);
    }
}
