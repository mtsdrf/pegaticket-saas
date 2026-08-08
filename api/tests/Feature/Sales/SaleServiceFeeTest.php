<?php

namespace Tests\Feature\Sales;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use App\Models\Sale\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Taxa de serviço PegaTicket (10%/mínimo R$3) — ver TicketFeeCalculator
 * para a regra pura e SaleService::create() para onde ela é aplicada.
 * Regra vigente global neste teste é sempre a default (10%/R$3), criada
 * por PlatformFinanceSettingsService::getCurrent() na primeira leitura.
 */
class SaleServiceFeeTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);

        $this->setUpTenantScopedUser('sale-fee-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function createTicketType(int $tenantId, string $feePayer, float $price, array $overrides = []): TicketType
    {
        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Category '.Str::random(6),
            'is_active' => true,
        ]);

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'event_category_id' => $category->id,
            'name' => 'Event '.Str::random(6),
            'slug' => 'event-'.Str::random(10),
            'type' => 'ingresso',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'visibility' => 'public',
            'status' => 'publicado',
            'service_fee_payer' => $feePayer,
        ]);

        return TicketType::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'name' => 'Ticket Type '.Str::random(6),
            'price' => $price,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ], $overrides));
    }

    #[Test]
    public function fee_payer_buyer_sums_the_fee_into_the_order_total(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        // R$20 -> 10% = R$2, abaixo do minimo de R$3 -> taxa unitaria R$3.
        $ticketType = $this->createTicketType($this->tenant->id, 'buyer', 20.00);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
            ],
        ]);

        // Subtotal 40.00 + taxa (2 x R$3.00 = R$6.00) = 46.00.
        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '46.00')
            ->assertJsonPath('data.platform_fee_total_amount', 6)
            ->assertJsonPath('data.platform_fee_payer_snapshot', 'buyer')
            ->assertJsonPath('data.items.0.platform_fee_unit_amount', 3)
            ->assertJsonPath('data.items.0.platform_fee_amount', 6);

        $this->assertDatabaseHas('sale_items', [
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $ticketType->id,
            'platform_fee_unit_amount' => '3.00',
            'platform_fee_amount' => '6.00',
            'platform_fee_percentage_snapshot' => '10.00',
            'platform_fee_minimum_snapshot' => '3.00',
            'platform_fee_rule_version_snapshot' => 1,
        ]);
    }

    #[Test]
    public function fee_payer_producer_does_not_change_the_order_total_but_persists_the_fee(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $ticketType = $this->createTicketType($this->tenant->id, 'producer', 20.00);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
            ],
        ]);

        // Subtotal 40.00 — produtor paga a taxa, comprador não vê acrescimo.
        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '40.00')
            ->assertJsonPath('data.platform_fee_total_amount', 6)
            ->assertJsonPath('data.platform_fee_payer_snapshot', 'producer');

        $sale = Sale::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertSame('40.00', $sale->total_amount);
        $this->assertSame('6.00', $sale->platform_fee_total_amount);
    }

    #[Test]
    public function two_different_ticket_types_have_their_fees_computed_separately_and_summed(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);

        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Category '.Str::random(6),
            'is_active' => true,
        ]);

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_category_id' => $category->id,
            'name' => 'Event '.Str::random(6),
            'slug' => 'event-'.Str::random(10),
            'type' => 'ingresso',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'visibility' => 'public',
            'status' => 'publicado',
            'service_fee_payer' => 'buyer',
        ]);

        $pista = TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'name' => 'Pista',
            'price' => 20.00,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);

        $vip = TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'name' => 'VIP',
            'price' => 100.00,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);

        // Pista: R$20 -> 10%=R$2, minimo R$3 -> R$3 cada, 2x = R$6.
        // VIP: R$100 -> 10%=R$10 (acima do minimo) -> R$10.
        // Taxa total = 6 + 10 = 16.00.
        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $pista->uuid, 'quantity' => 2],
                ['ticket_type_uuid' => $vip->uuid, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.platform_fee_total_amount', 16)
            // Subtotal 140.00 + taxa 16.00 = 156.00.
            ->assertJsonPath('data.total_amount', '156.00')
            ->assertJsonPath('data.items.0.platform_fee_amount', 6)
            ->assertJsonPath('data.items.1.platform_fee_amount', 10);
    }

    #[Test]
    public function a_lower_effective_unit_price_reduces_the_fee_base(): void
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        // Preço de tabela R$100 (taxa seria R$10), mas o item explicita
        // unit_price=40 (equivalente a um cupom já aplicado por linha,
        // mesmo mecanismo do storefront) -> taxa recalculada sobre R$40.
        $ticketType = $this->createTicketType($this->tenant->id, 'buyer', 100.00);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1, 'unit_price' => 40],
            ],
        ]);

        // 40*10% = 4.00 (acima do minimo) -> taxa R$4.00, nao R$10.
        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '40.00')
            ->assertJsonPath('data.items.0.platform_fee_amount', 4)
            ->assertJsonPath('data.platform_fee_total_amount', 4)
            ->assertJsonPath('data.total_amount', '44.00');
    }
}
