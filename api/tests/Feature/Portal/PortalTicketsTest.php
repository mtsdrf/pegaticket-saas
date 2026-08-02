<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use App\Models\Ticket\Ticket;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * GET /portal/sales/{uuid}/tickets — "Meus ingressos" do comprador final
 * (spec 5.15). Posse verificada via PortalCustomerService::findOwnedOrder()
 * (mesmo caminho de reorder/avaliação/cancelamento).
 */
class PortalTicketsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSaleFixtures;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant ' . Str::random(6),
            'slug' => 'tenant-' . Str::random(8),
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

    #[Test]
    public function returns_tickets_of_an_owned_and_linked_sale(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();
        $tenant = $this->createTenant();
        $ticketType = $this->createProduct($tenant->id, ['price' => 40]);

        $order = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'is_installment' => false,
            'total_amount' => 80,
            'is_paid' => true,
            'is_completed' => false,
        ]);

        $item = SaleItem::create([
            'tenant_id' => $tenant->id,
            'sale_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 40,
            'line_total' => 80,
        ]);

        Ticket::create([
            'tenant_id' => $tenant->id,
            'sale_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id,
            'status' => 'ativo',
            'issued_at' => now(),
        ]);

        Ticket::create([
            'tenant_id' => $tenant->id,
            'sale_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id,
            'status' => 'ativo',
            'issued_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $order->uuid])
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/portal/sales/{$order->uuid}/tickets");

        $response->assertStatus(200)->assertJsonCount(2, 'data');
        $this->assertNotEmpty($response->json('data.0.qr_token'));
    }

    #[Test]
    public function cannot_see_tickets_of_a_sale_belonging_to_another_customer(): void
    {
        [, $token] = $this->authenticatedCustomer('a@test.com');
        [$otherCustomer] = $this->authenticatedCustomer('b@test.com');

        $tenant = $this->createTenant();
        $order = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $otherCustomer->id,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => true,
            'is_completed' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/portal/sales/{$order->uuid}/tickets")
            ->assertStatus(404);
    }
}
