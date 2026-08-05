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
 * Revenda oficial verificada (roadmap Fase 4) — fechamento reaproveita
 * TicketService::transfer() (mesma trava/rotação de QR de
 * PortalTicketTransferTest), ver TicketResaleService.
 */
class TicketResaleTest extends TestCase
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

    private function authenticatedCustomer(string $email): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function linkCustomerToTenant(string $token, string $saleUuid): void
    {
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $saleUuid])
            ->assertStatus(200);
    }

    private function createOwnedTicket(Tenant $tenant, FinalCustomer $seller, string $sellerToken, array $ticketOverrides = [], float $price = 100): array
    {
        $ticketType = $this->createProduct($tenant->id, ['price' => $price]);

        $order = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $seller->id,
            'is_installment' => false,
            'total_amount' => $price,
            'is_paid' => true,
            'is_completed' => false,
        ]);

        $item = SaleItem::create([
            'tenant_id' => $tenant->id,
            'sale_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $price,
            'line_total' => $price,
        ]);

        $ticket = Ticket::create(array_merge([
            'tenant_id' => $tenant->id,
            'sale_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => 'Vendedor Original',
            'status' => 'ativo',
            'issued_at' => now(),
        ], $ticketOverrides));

        $this->linkCustomerToTenant($sellerToken, $order->uuid);

        return [$ticket, $order, $ticketType];
    }

    #[Test]
    public function rejects_listing_price_above_original_paid_amount(): void
    {
        $tenant = $this->createTenant();
        [$seller, $sellerToken] = $this->authenticatedCustomer('vendedor@test.com');
        [$ticket] = $this->createOwnedTicket($tenant, $seller, $sellerToken, [], 100);

        $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/resale-listings", ['asking_price' => 150])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_RESALE_LISTING_STATE');
    }

    #[Test]
    public function accepts_listing_price_at_or_below_original_paid_amount(): void
    {
        $tenant = $this->createTenant();
        [$seller, $sellerToken] = $this->authenticatedCustomer('vendedor@test.com');
        [$ticket] = $this->createOwnedTicket($tenant, $seller, $sellerToken, [], 100);

        $response = $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/resale-listings", ['asking_price' => 80]);

        $response->assertStatus(201);
        $this->assertSame('listado', $response->json('data.status'));
        $this->assertEquals(80, $response->json('data.asking_price'));
    }

    #[Test]
    public function only_current_owner_can_list_the_ticket(): void
    {
        $tenant = $this->createTenant();
        [$seller, $sellerToken] = $this->authenticatedCustomer('vendedor@test.com');
        [$ticket] = $this->createOwnedTicket($tenant, $seller, $sellerToken, [], 100);

        [, $strangerToken] = $this->authenticatedCustomer('estranho@test.com');

        $this->withHeader('Authorization', 'Bearer '.$strangerToken)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/resale-listings", ['asking_price' => 50])
            ->assertStatus(404);
    }

    #[Test]
    public function only_current_owner_can_cancel_the_listing(): void
    {
        $tenant = $this->createTenant();
        [$seller, $sellerToken] = $this->authenticatedCustomer('vendedor@test.com');
        [$ticket] = $this->createOwnedTicket($tenant, $seller, $sellerToken, [], 100);

        $listingUuid = $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/resale-listings", ['asking_price' => 80])
            ->json('data.uuid');

        [, $strangerToken] = $this->authenticatedCustomer('estranho@test.com');

        $this->withHeader('Authorization', 'Bearer '.$strangerToken)
            ->postJson("/api/v1/portal/resale-listings/{$listingUuid}/cancel")
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->postJson("/api/v1/portal/resale-listings/{$listingUuid}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelado');
    }

    #[Test]
    public function cannot_list_a_ticket_that_is_not_active(): void
    {
        $tenant = $this->createTenant();
        [$seller, $sellerToken] = $this->authenticatedCustomer('vendedor@test.com');
        [$ticket] = $this->createOwnedTicket($tenant, $seller, $sellerToken, ['status' => 'utilizado'], 100);

        $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/resale-listings", ['asking_price' => 80])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_RESALE_LISTING_STATE');
    }

    #[Test]
    public function successful_purchase_transfers_ownership_and_rotates_qr_credentials(): void
    {
        Mail::fake();

        $tenant = $this->createTenant();
        [$seller, $sellerToken] = $this->authenticatedCustomer('vendedor@test.com');
        [$ticket, $order] = $this->createOwnedTicket($tenant, $seller, $sellerToken, [], 100);

        $originalCode = $ticket->code;
        $originalQrToken = $ticket->qr_token;

        $listingUuid = $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/resale-listings", ['asking_price' => 80])
            ->json('data.uuid');

        [$buyer, $buyerToken] = $this->authenticatedCustomer('comprador@test.com');
        $this->linkCustomerToTenant($buyerToken, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer '.$buyerToken)
            ->postJson("/api/v1/portal/resale-listings/{$listingUuid}/purchase", ['attendee_document' => '99988877766']);

        $response->assertStatus(200);
        $this->assertSame('vendido', $response->json('data.status'));

        $ticket->refresh();
        $this->assertNotSame($originalCode, $ticket->code);
        $this->assertNotSame($originalQrToken, $ticket->qr_token);
        $this->assertSame('ativo', $ticket->status);
        $this->assertSame('99988877766', $ticket->attendee_document);

        Mail::assertSent(TicketDeliveryMail::class, fn ($mail) => $mail->mode === 'transferred');
    }

    #[Test]
    public function cannot_purchase_a_listing_that_was_already_sold(): void
    {
        $tenant = $this->createTenant();
        [$seller, $sellerToken] = $this->authenticatedCustomer('vendedor@test.com');
        [$ticket, $order] = $this->createOwnedTicket($tenant, $seller, $sellerToken, [], 100);

        $listingUuid = $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->postJson("/api/v1/portal/tickets/{$ticket->uuid}/resale-listings", ['asking_price' => 80])
            ->json('data.uuid');

        [, $buyerToken] = $this->authenticatedCustomer('comprador@test.com');
        $this->linkCustomerToTenant($buyerToken, $order->uuid);

        $this->withHeader('Authorization', 'Bearer '.$buyerToken)
            ->postJson("/api/v1/portal/resale-listings/{$listingUuid}/purchase")
            ->assertStatus(200);

        [, $secondBuyerToken] = $this->authenticatedCustomer('comprador2@test.com');
        $this->linkCustomerToTenant($secondBuyerToken, $order->uuid);

        $this->withHeader('Authorization', 'Bearer '.$secondBuyerToken)
            ->postJson("/api/v1/portal/resale-listings/{$listingUuid}/purchase")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_RESALE_LISTING_STATE');
    }

    #[Test]
    public function browsing_listings_of_one_tenant_never_leaks_another_tenants_listings(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        [$sellerA, $sellerATokenA] = $this->authenticatedCustomer('vendedorA@test.com');
        [$ticketA] = $this->createOwnedTicket($tenantA, $sellerA, $sellerATokenA, [], 100);
        $listingA = $this->withHeader('Authorization', 'Bearer '.$sellerATokenA)
            ->postJson("/api/v1/portal/tickets/{$ticketA->uuid}/resale-listings", ['asking_price' => 80])
            ->json('data');

        [$sellerB, $sellerTokenB] = $this->authenticatedCustomer('vendedorB@test.com');
        [$ticketB] = $this->createOwnedTicket($tenantB, $sellerB, $sellerTokenB, [], 100);
        $this->withHeader('Authorization', 'Bearer '.$sellerTokenB)
            ->postJson("/api/v1/portal/tickets/{$ticketB->uuid}/resale-listings", ['asking_price' => 60])
            ->assertStatus(201);

        $eventUuidTenantA = $ticketA->ticketType->event->uuid;

        $response = $this->withHeader('Authorization', 'Bearer '.$sellerATokenA)
            ->getJson('/api/v1/portal/resale-listings?event_uuid='.$eventUuidTenantA);

        $response->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('uuid')->all();
        $this->assertContains($listingA['uuid'], $uuids);
        $this->assertCount(1, $uuids);
    }
}
