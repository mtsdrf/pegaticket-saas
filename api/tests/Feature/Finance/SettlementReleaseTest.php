<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\TenantSettings;
use App\Services\Finance\SettlementReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class SettlementReleaseTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-pagbank-token');

        $this->setUpTenantScopedUser('settlement-release@test.com');
    }

    #[Test]
    public function releases_a_settlement_via_pagbank_split_custody_and_writes_ledger(): void
    {
        TenantSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['pagbank_receiver_account_id' => 'ACCO_TENANT_1']
        );

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-TEST-1',
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 95,
            'metadata' => [
                'provider_split_ids' => ['SPLI_REL_1'],
            ],
        ]);

        $this->createReceivable($settlement->id, 'SPLI_REL_1');

        Http::fake([
            'sandbox.api.pagseguro.com/splits/SPLI_REL_1' => Http::sequence()
                ->push([
                    'id' => 'SPLI_REL_1',
                    'receivers' => [[
                        'account' => ['id' => 'ACCO_TENANT_1'],
                        'configurations' => [
                            'custody' => [
                                'status' => 'HELD',
                                'release' => [
                                    'scheduled' => now()->toIso8601String(),
                                    'released_at' => null,
                                ],
                            ],
                        ],
                    ]],
                ], 200)
                ->push([
                    'id' => 'SPLI_REL_1',
                    'receivers' => [[
                        'account' => ['id' => 'ACCO_TENANT_1'],
                        'configurations' => [
                            'custody' => [
                                'status' => 'RELEASED',
                                'release' => [
                                    'scheduled' => now()->toIso8601String(),
                                    'released_at' => now()->toIso8601String(),
                                ],
                            ],
                        ],
                    ]],
                ], 200),
            'sandbox.api.pagseguro.com/splits/SPLI_REL_1/custody/release' => Http::response([], 200),
        ]);

        $result = app(SettlementReleaseService::class)->releaseDue(now());

        $settlement->refresh();

        $this->assertSame([
            'settlements_seen' => 1,
            'released' => 1,
            'release_requested' => 0,
            'skipped' => 0,
        ], $result);
        $this->assertSame('released', $settlement->status);
        $this->assertNotNull($settlement->released_at);
        $this->assertSame('pagbank_split_custody', $settlement->metadata['release_mode']);
        $this->assertSame(['SPLI_REL_1'], $settlement->metadata['released_split_ids']);
        $this->assertDatabaseHas('receivables', [
            'settlement_id' => $settlement->id,
            'status' => 'released',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'settlement_id' => $settlement->id,
            'entry_type' => 'settlement_release',
            'direction' => 'debit',
            'amount' => 95,
        ]);

        Http::assertSentCount(3);
    }

    #[Test]
    public function releases_a_settlement_locally_when_no_split_release_is_needed(): void
    {
        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-TEST-2',
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
            'gross_amount' => 80,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 75,
        ]);

        $this->createReceivable($settlement->id, null);

        $result = app(SettlementReleaseService::class)->releaseDue(now());

        $settlement->refresh();

        $this->assertSame(1, $result['released']);
        $this->assertSame('released', $settlement->status);
        $this->assertSame('local_only', $settlement->metadata['release_mode']);
        $this->assertDatabaseHas('receivables', [
            'settlement_id' => $settlement->id,
            'status' => 'released',
        ]);
        $this->assertSame(1, LedgerEntry::query()->where('settlement_id', $settlement->id)->count());
    }

    #[Test]
    public function reconciles_a_release_requested_settlement_when_pagbank_later_reflects_released(): void
    {
        TenantSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['pagbank_receiver_account_id' => 'ACCO_TENANT_2']
        );

        $requestedAt = now()->subMinutes(10)->toIso8601String();
        $releasedAt = now()->subMinutes(2)->toIso8601String();

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-TEST-3',
            'status' => 'release_requested',
            'scheduled_for' => now()->subHour(),
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 95,
            'metadata' => [
                'provider_split_ids' => ['SPLI_REL_2'],
                'release_requested_at' => $requestedAt,
            ],
        ]);

        $this->createReceivable($settlement->id, 'SPLI_REL_2', 'release_requested');

        Http::fake([
            'sandbox.api.pagseguro.com/splits/SPLI_REL_2' => Http::response([
                'id' => 'SPLI_REL_2',
                'receivers' => [[
                    'account' => ['id' => 'ACCO_TENANT_2'],
                    'configurations' => [
                        'custody' => [
                            'status' => 'RELEASED',
                            'release' => [
                                'scheduled' => now()->toIso8601String(),
                                'released_at' => $releasedAt,
                            ],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $result = app(SettlementReleaseService::class)->reconcileRequested();

        $settlement->refresh();

        $this->assertSame([
            'settlements_seen' => 1,
            'released' => 1,
            'still_pending' => 0,
            'skipped' => 0,
        ], $result);
        $this->assertSame('released', $settlement->status);
        $this->assertSame($releasedAt, $settlement->released_at?->toIso8601String());
        $this->assertSame(['SPLI_REL_2'], $settlement->metadata['released_split_ids']);
        $this->assertArrayHasKey('last_release_reconciliation_at', $settlement->metadata);
        $this->assertDatabaseHas('receivables', [
            'settlement_id' => $settlement->id,
            'status' => 'released',
        ]);
        $this->assertSame(1, LedgerEntry::query()->where('settlement_id', $settlement->id)->count());
    }

    private function createReceivable(int $settlementId, ?string $splitId, string $status = 'awaiting_release'): void
    {
        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);
        $sale = $this->createSale($client->id, $product->id);

        Receivable::create([
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale['id'],
            'event_id' => $product->event_id,
            'settlement_id' => $settlementId,
            'status' => $status,
            'currency' => 'BRL',
            'gross_amount' => '100.00',
            'platform_fee_amount' => '5.00',
            'processor_fee_amount' => '0.00',
            'net_amount' => '95.00',
            'settlement_reference' => 'event_end_d_plus_1',
            'event_ends_at' => now()->subDay(),
            'available_at' => now()->subMinute(),
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_'.Str::upper(Str::random(8)),
            'provider_split_id' => $splitId,
        ]);
    }

    /**
     * @return array{id:int}
     */
    private function createSale(int $clientId, int $ticketTypeId): array
    {
        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $clientId,
            'codigo' => 'SALE-REL-'.Str::upper(Str::random(4)),
            'total_amount' => 100,
            'paid_amount' => 100,
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
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        return ['id' => $sale->id];
    }
}
