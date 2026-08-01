<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * "Pedir de novo" (roadmap Delivery, Fase 4 — retenção):
 * GET /portal/sales/{uuid}/items, preço/disponibilidade ATUAIS do produto,
 * nunca o preço congelado no pedido antigo.
 */
class PortalResaleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSaleFixtures;

    private function authenticatedCustomer(string $email): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

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

    private function createOrderWithItem(Tenant $tenant, FinalCustomer $owner, $product, float $quantity = 2, float $unitPrice = 5): Sale
    {

        $order = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $owner->id,
            'is_installment' => false,
            'total_amount' => $quantity * $unitPrice,
            'is_paid' => false,
            'is_delivered' => false,
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'ticket_type_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);

        return $order;
    }

    private function linkCustomerToOrder(string $token, string $orderUuid): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $orderUuid])
            ->assertStatus(200);
    }

    #[Test]
    public function returns_items_with_current_price_not_the_frozen_one(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $product = $this->createProduct($tenant->id, ['price' => 10]);

        $order = $this->createOrderWithItem($tenant, $customer, $product, 2, 5);
        $this->linkCustomerToOrder($token, $order->uuid);

        // Preço do produto mudou desde a compra antiga.
        $product->update(['price' => 15]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/sales/' . $order->uuid . '/items');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.ticket_type_uuid', $product->uuid)
            ->assertJsonPath('data.items.0.ticket_type_name', $product->name)
            ->assertJsonPath('data.items.0.is_available', true);

        $this->assertEquals(2.0, $response->json('data.items.0.quantity'));
        $this->assertEquals(15.0, $response->json('data.items.0.current_price'));
    }

    #[Test]
    public function marks_item_unavailable_when_product_was_soft_deleted(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $product = $this->createProduct($tenant->id, ['name' => 'Removido do catálogo']);

        $order = $this->createOrderWithItem($tenant, $customer, $product);
        $this->linkCustomerToOrder($token, $order->uuid);

        $product->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/sales/' . $order->uuid . '/items');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.ticket_type_name', 'Removido do catálogo')
            ->assertJsonPath('data.items.0.is_available', false);
    }

    #[Test]
    public function marks_item_unavailable_when_product_is_flagged_unavailable(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $product = $this->createProduct($tenant->id, ['status' => 'ativo']);

        $order = $this->createOrderWithItem($tenant, $customer, $product);
        $this->linkCustomerToOrder($token, $order->uuid);

        $product->update(['status' => 'pausado']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/sales/' . $order->uuid . '/items');

        $response->assertStatus(200)->assertJsonPath('data.items.0.is_available', false);
    }

    #[Test]
    public function returns_404_for_order_belonging_to_another_customer(): void
    {
        [$customerA, $tokenA] = $this->authenticatedCustomer('a@test.com');
        [, $tokenB] = $this->authenticatedCustomer('b@test.com');

        $tenant = $this->createTenant();
        $product = $this->createProduct($tenant->id);
        $order = $this->createOrderWithItem($tenant, $customerA, $product);

        $this->linkCustomerToOrder($tokenA, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/v1/portal/sales/' . $order->uuid . '/items')
            ->assertStatus(404);
    }

    #[Test]
    public function returns_404_for_nonexistent_order_uuid(): void
    {
        [, $token] = $this->authenticatedCustomer('cliente@test.com');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/sales/' . Str::uuid() . '/items')
            ->assertStatus(404);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->getJson('/api/v1/portal/sales/' . Str::uuid() . '/items')->assertStatus(401);
    }
}
