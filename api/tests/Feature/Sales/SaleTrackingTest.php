<?php

namespace Tests\Feature\Orders;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleInstallment;
use App\Models\Sale\SaleItem;
use App\Models\Event\TicketType;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Rota pública GET /rastreio/{order:uuid} (roadmap 5.1) — sem jwt/tenant/
 * perm, protegida só pelo uuid ser imprevisível. Ver
 * App\Http\Controllers\Sale\SaleTrackingController.
 */
class SaleTrackingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

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

    private function createOrder(Tenant $tenant, FinalCustomer $client, StockLocation $location, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
            'notes' => 'Observação interna, nunca deve vazar.',
        ], $overrides));
    }

    private function createOrderItem(Tenant $tenant, Sale $order, TicketType $product, array $overrides = []): SaleItem
    {
        return SaleItem::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'ticket_type_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 38,
            'line_total' => 114,
        ], $overrides));
    }

    private function createInstallment(Tenant $tenant, Sale $order, array $overrides = []): SaleInstallment
    {
        return SaleInstallment::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'installment_number' => 1,
            'amount' => 60,
            'due_date' => now()->addMonth()->toDateString(),
            'is_paid' => false,
        ], $overrides));
    }

    #[Test]
    public function returns_200_without_any_authentication_header(): void
    {
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);
        $order = $this->createOrder($tenant, $client, $location);

        $response = $this->getJson('/api/v1/rastreio/' . $order->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $order->uuid)
            ->assertJsonPath('data.tenant_name', $tenant->name)
            ->assertJsonPath('data.final_customer_name', $client->name);
    }

    #[Test]
    public function returns_items_and_omits_installments_when_order_is_not_installment(): void
    {
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);
        $product = $this->createProduct($tenant->id, ['name' => 'Queijo Meia Cura']);
        $order = $this->createOrder($tenant, $client, $location, ['is_installment' => false]);
        $this->createOrderItem($tenant, $order, $product);

        $response = $this->getJson('/api/v1/rastreio/' . $order->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_installment', false)
            ->assertJsonPath('data.items.0.ticket_type_name', 'Queijo Meia Cura')
            ->assertJsonPath('data.items.0.quantity', '3.000')
            ->assertJsonPath('data.items.0.line_total', '114.00')
            ->assertJsonPath('data.installments', []);
    }

    #[Test]
    public function returns_installments_when_order_is_installment(): void
    {
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);
        $product = $this->createProduct($tenant->id);
        $order = $this->createOrder($tenant, $client, $location, ['is_installment' => true]);
        $this->createOrderItem($tenant, $order, $product);
        $this->createInstallment($tenant, $order, ['installment_number' => 1, 'amount' => 60]);
        $this->createInstallment($tenant, $order, ['installment_number' => 2, 'amount' => 60, 'is_paid' => true, 'paid_at' => now()]);

        $response = $this->getJson('/api/v1/rastreio/' . $order->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_installment', true)
            ->assertJsonPath('data.installments.0.installment_number', 1)
            ->assertJsonPath('data.installments.0.amount', '60.00')
            ->assertJsonPath('data.installments.0.is_paid', false)
            ->assertJsonPath('data.installments.1.installment_number', 2)
            ->assertJsonPath('data.installments.1.is_paid', true);
    }

    #[Test]
    public function is_cancelled_reflects_cancelled_at(): void
    {
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);

        $activeOrder = $this->createOrder($tenant, $client, $location);
        $cancelledOrder = $this->createOrder($tenant, $client, $location, [
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cliente desistiu.',
        ]);

        $this->getJson('/api/v1/rastreio/' . $activeOrder->uuid)
            ->assertJsonPath('data.is_cancelled', false);

        $this->getJson('/api/v1/rastreio/' . $cancelledOrder->uuid)
            ->assertJsonPath('data.is_cancelled', true);
    }

    #[Test]
    public function response_never_contains_notes_or_cancellation_reason(): void
    {
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);
        $order = $this->createOrder($tenant, $client, $location, [
            'notes' => 'Segredo interno da equipe.',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Motivo interno sensível.',
        ]);

        $response = $this->getJson('/api/v1/rastreio/' . $order->uuid);

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.cancellation_reason');
    }

    #[Test]
    public function order_from_any_tenant_is_reachable_by_its_own_uuid(): void
    {
        $tenantA = $this->createTenant();
        $clientA = $this->createClient($tenantA->id);
        $locationA = $this->createLocation($tenantA->id);
        $orderA = $this->createOrder($tenantA, $clientA, $locationA);

        $tenantB = $this->createTenant();
        $clientB = $this->createClient($tenantB->id);
        $locationB = $this->createLocation($tenantB->id);
        $orderB = $this->createOrder($tenantB, $clientB, $locationB);

        $this->getJson('/api/v1/rastreio/' . $orderA->uuid)
            ->assertStatus(200)
            ->assertJsonPath('data.tenant_name', $tenantA->name)
            ->assertJsonPath('data.final_customer_name', $clientA->name);

        $this->getJson('/api/v1/rastreio/' . $orderB->uuid)
            ->assertStatus(200)
            ->assertJsonPath('data.tenant_name', $tenantB->name)
            ->assertJsonPath('data.final_customer_name', $clientB->name);
    }

    #[Test]
    public function returns_404_for_nonexistent_uuid(): void
    {
        $this->getJson('/api/v1/rastreio/' . Str::uuid())
            ->assertStatus(404);
    }

    #[Test]
    public function returns_404_for_soft_deleted_order(): void
    {
        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);
        $order = $this->createOrder($tenant, $client, $location);

        $order->delete();

        $this->getJson('/api/v1/rastreio/' . $order->uuid)
            ->assertStatus(404);
    }
}
