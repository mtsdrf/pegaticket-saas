<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Services\Finance\SettlementGenerationService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class SettlementGenerationTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('settlements@test.com');
    }

    #[Test]
    public function generates_one_settlement_for_receivables_of_the_same_tenant_event_and_date(): void
    {
        $eventProduct = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $saleA = $this->createPaidSale($client->id, $eventProduct->id, 'SALE-SET-1', 100);
        $saleB = $this->createPaidSale($client->id, $eventProduct->id, 'SALE-SET-2', 80);

        // Ancorado num horário fixo do dia anterior (não now()->subHour()/
        // subMinutes()) — evita teste flaky perto da virada de meia-noite
        // UTC, onde subHour() e subMinutes() podem cair em datas
        // calendário diferentes e quebrar o agrupamento por date(available_at).
        $anchor = now()->subDay()->setTime(12, 0);
        $this->createReceivable($saleA->id, $eventProduct->event_id, 100, 5, 95, $anchor, 'SPLI_A');
        $this->createReceivable($saleB->id, $eventProduct->event_id, 80, 5, 75, $anchor->copy()->addMinutes(15), 'SPLI_B');

        $result = app(SettlementGenerationService::class)->generateAvailable(now());

        $this->assertSame([
            'groups_seen' => 1,
            'settlements_created' => 1,
            'receivables_linked' => 2,
        ], $result);

        $settlement = Settlement::query()->firstOrFail();

        $this->assertSame($this->tenant->id, $settlement->tenant_id);
        $this->assertSame('180.00', $settlement->gross_amount);
        $this->assertSame('10.00', $settlement->platform_fee_amount);
        $this->assertSame('170.00', $settlement->net_amount);
        $this->assertSame($eventProduct->event_id, $settlement->metadata['event_id']);
        $this->assertSame(2, $settlement->metadata['receivable_count']);
        $this->assertSame(['SPLI_A', 'SPLI_B'], $settlement->metadata['provider_split_ids']);

        $this->assertDatabaseCount('settlements', 1);
        $this->assertDatabaseHas('receivables', [
            'sale_id' => $saleA->id,
            'settlement_id' => $settlement->id,
            'status' => 'awaiting_release',
        ]);
        $this->assertDatabaseHas('receivables', [
            'sale_id' => $saleB->id,
            'settlement_id' => $settlement->id,
            'status' => 'awaiting_release',
        ]);
    }

    #[Test]
    public function generation_is_idempotent_and_ignores_future_or_already_linked_receivables(): void
    {
        $eventProduct = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $saleEligible = $this->createPaidSale($client->id, $eventProduct->id, 'SALE-SET-3', 50);
        $saleFuture = $this->createPaidSale($client->id, $eventProduct->id, 'SALE-SET-4', 60);
        $saleLinked = $this->createPaidSale($client->id, $eventProduct->id, 'SALE-SET-5', 70);

        $eligible = $this->createReceivable($saleEligible->id, $eventProduct->event_id, 50, 5, 45, now()->subMinute(), 'SPLI_C');
        $this->createReceivable($saleFuture->id, $eventProduct->event_id, 60, 5, 55, now()->addDay(), 'SPLI_D');

        $existingSettlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-MANUAL-1',
            'status' => 'scheduled',
            'scheduled_for' => now(),
            'gross_amount' => 70,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 65,
        ]);

        $this->createReceivable(
            $saleLinked->id,
            $eventProduct->event_id,
            70,
            5,
            65,
            now()->subMinute(),
            'SPLI_E',
            $existingSettlement->id,
            'awaiting_release'
        );

        $service = app(SettlementGenerationService::class);

        $firstResult = $service->generateAvailable(now());
        $secondResult = $service->generateAvailable(now());

        $this->assertSame(1, $firstResult['settlements_created']);
        $this->assertSame(1, $firstResult['receivables_linked']);
        $this->assertSame(0, $secondResult['settlements_created']);
        $this->assertSame(0, $secondResult['receivables_linked']);

        $eligible->refresh();

        $this->assertNotNull($eligible->settlement_id);
        $this->assertSame('awaiting_release', $eligible->status);
        $this->assertDatabaseCount('settlements', 2);
        $this->assertDatabaseHas('receivables', [
            'sale_id' => $saleFuture->id,
            'settlement_id' => null,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('receivables', [
            'sale_id' => $saleLinked->id,
            'settlement_id' => $existingSettlement->id,
            'status' => 'awaiting_release',
        ]);
    }

    private function createPaidSale(int $clientId, int $ticketTypeId, string $code, float $amount): Sale
    {
        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $clientId,
            'codigo' => $code,
            'total_amount' => $amount,
            'paid_amount' => $amount,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketTypeId,
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);

        return $sale;
    }

    private function createReceivable(
        int $saleId,
        ?int $eventId,
        float $grossAmount,
        float $platformFeeAmount,
        float $netAmount,
        CarbonInterface $availableAt,
        ?string $providerSplitId,
        ?int $settlementId = null,
        string $status = 'scheduled',
    ): Receivable {
        return Receivable::create([
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleId,
            'event_id' => $eventId,
            'settlement_id' => $settlementId,
            'status' => $status,
            'currency' => 'BRL',
            'gross_amount' => number_format($grossAmount, 2, '.', ''),
            'platform_fee_amount' => number_format($platformFeeAmount, 2, '.', ''),
            'processor_fee_amount' => '0.00',
            'net_amount' => number_format($netAmount, 2, '.', ''),
            'settlement_reference' => 'event_end_d_plus_1',
            'event_ends_at' => now()->subDay(),
            'available_at' => $availableAt,
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_'.Str::upper(Str::random(8)),
            'provider_split_id' => $providerSplitId,
        ]);
    }
}
