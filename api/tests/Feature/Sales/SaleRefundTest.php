<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Ticket\Ticket;
use App\Models\Venue\Seat;
use App\Models\Venue\Venue;
use App\Models\Venue\VenueMapVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Estorno externo (spec 5.14/11.3) — POST/GET /sales/{order}/refunds.
 * O sistema NÃO fala com o PagBank aqui: só registra o estorno já feito
 * manualmente pelo clube e aplica os efeitos internos (invalidar
 * tickets, opcionalmente liberar o lugar). Ver App\Services\Sale\SaleRefundService.
 */
class SaleRefundTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('sale-refund-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sale_refunds', 'create');
        $this->grantPermission('sale_refunds', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    /**
     * @return array{order: array, tickets: Collection<int, Ticket>}
     */
    private function createSeat(string $label): Seat
    {
        $venue = Venue::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Venue '.Str::random(6),
            'is_active' => true,
        ]);

        $mapVersion = VenueMapVersion::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'venue_id' => $venue->id,
            'version_number' => 1,
            'is_published' => true,
        ]);

        return Seat::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'venue_map_version_id' => $mapVersion->id,
            'label' => $label,
            'kind' => 'assento',
            'capacity' => 1,
            'status' => 'disponivel',
        ]);
    }

    private function createPaidOrderWithTickets(int $quantity = 2, float $price = 25): array
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => $price]);

        $items = [];
        for ($i = 0; $i < $quantity; $i++) {
            $items[] = ['ticket_type_uuid' => $product->uuid, 'quantity' => 1];
        }

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => $items,
        ])->assertStatus(201)->json('data');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $tickets = Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $saleId))->get();

        return ['order' => $order, 'tickets' => $tickets];
    }

    private function createReceivableForOrderUuid(
        string $orderUuid,
        float $grossAmount,
        float $platformFeeAmount,
        float $netAmount,
        ?string $status = 'scheduled',
        ?Settlement $settlement = null,
    ): Receivable {
        $sale = Sale::where('uuid', $orderUuid)->firstOrFail();

        $receivable = Receivable::where('sale_id', $sale->id)->first();

        if ($receivable === null) {
            return Receivable::create([
                'tenant_id' => $sale->tenant_id,
                'sale_id' => $sale->id,
                'event_id' => null,
                'settlement_id' => $settlement?->id,
                'status' => $status,
                'currency' => 'BRL',
                'gross_amount' => number_format($grossAmount, 2, '.', ''),
                'platform_fee_amount' => number_format($platformFeeAmount, 2, '.', ''),
                'processor_fee_amount' => '0.00',
                'net_amount' => number_format($netAmount, 2, '.', ''),
                'settlement_reference' => 'event_end_d_plus_1',
                'event_ends_at' => now()->subDay(),
                'available_at' => now()->subHour(),
                'provider' => 'pagbank',
                'provider_charge_id' => 'ORDE_REFUND_TEST',
                'provider_split_id' => 'SPLI_REFUND_TEST',
            ]);
        }

        $receivable->fill([
            'settlement_id' => $settlement?->id,
            'status' => $status,
            'gross_amount' => number_format($grossAmount, 2, '.', ''),
            'platform_fee_amount' => number_format($platformFeeAmount, 2, '.', ''),
            'processor_fee_amount' => '0.00',
            'net_amount' => number_format($netAmount, 2, '.', ''),
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_REFUND_TEST',
            'provider_split_id' => 'SPLI_REFUND_TEST',
        ]);
        $receivable->save();

        return $receivable->fresh();
    }

    #[Test]
    public function operator_registers_a_total_refund_and_all_tickets_become_estornado(): void
    {
        ['order' => $order, 'tickets' => $tickets] = $this->createPaidOrderWithTickets(2, 25);
        $this->assertCount(2, $tickets);
        $paidAmount = (float) Sale::where('uuid', $order['uuid'])->value('paid_amount');

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => $paidAmount,
            'reason' => 'Show cancelado pelo clube',
            'refunded_at' => '2026-08-01',
            'external_reference' => 'PAGBANK-123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'total')
            ->assertJsonPath('data.status', 'registrado');

        foreach ($tickets as $ticket) {
            $this->assertSame('estornado', $ticket->fresh()->status);
        }

        $this->assertDatabaseCount('sale_refund_tickets', 2);
    }

    #[Test]
    public function refunded_ticket_can_no_longer_check_in(): void
    {
        ['order' => $order, 'tickets' => $tickets] = $this->createPaidOrderWithTickets(1, 25);
        $ticket = $tickets->first();

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'Comprador desistiu',
            'refunded_at' => '2026-08-01',
        ])->assertStatus(201);

        $this->grantPermission('tickets', 'checkin');

        $checkin = $this->auth()->postJson('/api/v1/tickets/checkin', [
            'qr_token' => $ticket->fresh()->qr_token,
        ]);

        $checkin->assertStatus(200)->assertJsonPath('data.result', 'estornado');
        $this->assertSame('estornado', $ticket->fresh()->status);
    }

    #[Test]
    public function amount_cannot_exceed_paid_amount_even_summing_previous_refunds(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(2, 25); // paid_amount = 50

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 40,
            'reason' => 'Primeiro estorno parcial do valor',
            'refunded_at' => '2026-08-01',
        ])->assertStatus(201);

        // Só sobram 10 disponíveis (50 pago - 40 já estornado).
        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 15,
            'reason' => 'Segundo estorno excede o disponível',
            'refunded_at' => '2026-08-02',
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertDatabaseCount('sale_refunds', 1);
    }

    #[Test]
    public function partial_refund_without_ticket_list_is_rejected(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(2, 25);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'parcial',
            'amount' => 25,
            'reason' => 'Só um dos dois ingressos',
            'refunded_at' => '2026-08-01',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sale_refunds', 0);
    }

    #[Test]
    public function partial_refund_only_marks_the_selected_tickets(): void
    {
        ['order' => $order, 'tickets' => $tickets] = $this->createPaidOrderWithTickets(2, 25);
        $refundedTicket = $tickets->first();
        $keptTicket = $tickets->last();

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'parcial',
            'amount' => 25,
            'reason' => 'Um dos participantes desistiu',
            'refunded_at' => '2026-08-01',
            'ticket_uuids' => [$refundedTicket->uuid],
        ]);

        $response->assertStatus(201)->assertJsonPath('data.type', 'parcial');

        $this->assertSame('estornado', $refundedTicket->fresh()->status);
        $this->assertNotSame('estornado', $keptTicket->fresh()->status);
        $this->assertDatabaseCount('sale_refund_tickets', 1);
    }

    #[Test]
    public function partial_refund_rejects_a_ticket_uuid_that_does_not_belong_to_the_order(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(1, 25);
        ['tickets' => $otherTickets] = $this->createPaidOrderWithTickets(1, 25);
        $foreignTicket = $otherTickets->first();

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'parcial',
            'amount' => 25,
            'reason' => 'Ticket de outra venda',
            'refunded_at' => '2026-08-01',
            'ticket_uuids' => [$foreignTicket->uuid],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
        $this->assertDatabaseCount('sale_refunds', 0);
    }

    #[Test]
    public function a_ticket_already_refunded_cannot_be_refunded_again(): void
    {
        ['order' => $order, 'tickets' => $tickets] = $this->createPaidOrderWithTickets(1, 25);
        $ticket = $tickets->first();

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'Primeiro estorno',
            'refunded_at' => '2026-08-01',
        ])->assertStatus(201);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'parcial',
            'amount' => 1,
            'reason' => 'Tenta estornar de novo o mesmo ticket',
            'refunded_at' => '2026-08-02',
            'ticket_uuids' => [$ticket->uuid],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function refund_registration_is_audited(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(1, 25);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'Auditoria do estorno',
            'refunded_at' => '2026-08-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'order_refund_created',
        ]);

        $log = AuditLog::where('event', 'order_refund_created')->first();
        $this->assertSame($order['uuid'], $log->meta['sale_uuid']);
        $this->assertSame('total', $log->meta['type']);
    }

    #[Test]
    public function listing_returns_refunds_of_the_order(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(1, 25);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'Listagem',
            'refunded_at' => '2026-08-01',
        ])->assertStatus(201);

        $response = $this->auth()->getJson("/api/v1/sales/{$order['uuid']}/refunds");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function order_from_another_tenant_returns_404(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(1, 25);

        $this->setUpTenantScopedUser('sale-refund-user-other@test.com');
        $this->grantPermission('sale_refunds', 'create');
        $this->grantPermission('sale_refunds', 'read');

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'x',
            'refunded_at' => '2026-08-01',
        ])->assertStatus(404);

        $this->auth()->getJson("/api/v1/sales/{$order['uuid']}/refunds")
            ->assertStatus(404);
    }

    /**
     * Decisão técnica documentada em SaleRefundService: a disponibilidade
     * de assento é calculada em StorefrontHoldService::buildSeatAvailability()
     * somando sale_items.seat_id não-nulo de vendas não cancelados —
     * NUNCA olha para Ticket.status. "Liberar lugar" só tem efeito real se
     * o seat_id do order_item (e do ticket) for nulado; sem
     * release_seats=true, o item some do ticket mas o vínculo com o
     * assento permanece intacto.
     */
    #[Test]
    public function release_seats_true_nulls_seat_id_on_ticket_and_order_item(): void
    {
        $seat = $this->createSeat('A1');

        ['order' => $order, 'tickets' => $tickets] = $this->createPaidOrderWithTickets(1, 25);
        $ticket = $tickets->first();
        $saleItemId = $ticket->sale_item_id;

        // Sem seat vinculado no fixture (createProduct não usa mapa de
        // assento) — associa manualmente pra simular o cenário de venda
        // com assento reservado.
        $ticket->update(['seat_id' => $seat->id]);
        SaleItem::whereKey($saleItemId)->update(['seat_id' => $seat->id]);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'Libera o lugar para revenda',
            'refunded_at' => '2026-08-01',
            'release_seats' => true,
        ])->assertStatus(201);

        $this->assertNull($ticket->fresh()->seat_id);
        $this->assertNull(SaleItem::whereKey($saleItemId)->value('seat_id'));
        $this->assertSame('estornado', $ticket->fresh()->status);
    }

    #[Test]
    public function release_seats_false_keeps_seat_id_linked_even_though_ticket_is_estornado(): void
    {
        $seat = $this->createSeat('A2');

        ['order' => $order, 'tickets' => $tickets] = $this->createPaidOrderWithTickets(1, 25);
        $ticket = $tickets->first();
        $saleItemId = $ticket->sale_item_id;

        $ticket->update(['seat_id' => $seat->id]);
        SaleItem::whereKey($saleItemId)->update(['seat_id' => $seat->id]);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'Estorna mas mantém bloqueio do lugar',
            'refunded_at' => '2026-08-01',
            'release_seats' => false,
        ])->assertStatus(201);

        $this->assertSame($seat->id, $ticket->fresh()->seat_id);
        $this->assertSame($seat->id, SaleItem::whereKey($saleItemId)->value('seat_id'));
        $this->assertSame('estornado', $ticket->fresh()->status);
    }

    #[Test]
    public function receipt_upload_is_stored_and_downloadable_only_through_the_authenticated_endpoint(): void
    {
        Storage::fake('local');

        ['order' => $order] = $this->createPaidOrderWithTickets(1, 25);

        $file = UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf');

        $response = $this->auth()->post("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 25,
            'reason' => 'Com comprovante',
            'refunded_at' => '2026-08-01',
            'receipt' => $file,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.has_receipt', true);

        $refundUuid = $response->json('data.uuid');

        $download = $this->auth()->get("/api/v1/sales/{$order['uuid']}/refunds/{$refundUuid}/receipt");
        $download->assertStatus(200);

        // Sem autenticação, a rota é bloqueada (nunca serve por URL
        // pública) — flushHeaders() remove o Authorization persistido
        // pelas chamadas anteriores via $this->auth() nesta mesma classe
        // de teste (withHeader() acumula em $this->defaultHeaders).
        $this->flushHeaders();
        $this->getJson("/api/v1/sales/{$order['uuid']}/refunds/{$refundUuid}/receipt")
            ->assertStatus(401);
    }

    #[Test]
    public function refund_before_release_creates_an_applied_financial_adjustment_and_reduces_open_amounts(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(1, 25);

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-REFUND-OPEN',
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
            'gross_amount' => 25,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 20,
        ]);

        $receivable = $this->createReceivableForOrderUuid(
            orderUuid: $order['uuid'],
            grossAmount: 25,
            platformFeeAmount: 5,
            netAmount: 20,
            status: 'awaiting_release',
            settlement: $settlement,
        );

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 10,
            'reason' => 'Estorno antes do repasse',
            'refunded_at' => '2026-08-03',
        ])->assertStatus(201);

        $receivable->refresh();
        $settlement->refresh();

        $adjustment = SettlementAdjustment::query()
            ->where('receivable_id', $receivable->id)
            ->firstOrFail();

        $this->assertSame('refund_organizer_deduction', $adjustment->type);
        $this->assertSame('applied', $adjustment->status);
        $this->assertSame('10.00', $adjustment->amount);
        $this->assertSame('10.00', $receivable->net_amount);
        $this->assertSame('10.00', $settlement->net_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'settlement_adjustment_id' => $adjustment->id,
            'entry_type' => 'refund_adjustment_applied',
            'amount' => 10,
        ]);
    }

    #[Test]
    public function refund_after_release_creates_pending_recovery_and_platform_exposure_when_needed(): void
    {
        ['order' => $order] = $this->createPaidOrderWithTickets(1, 25);

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-REFUND-RELEASED',
            'status' => 'released',
            'scheduled_for' => now()->subDay(),
            'released_at' => now()->subHours(2),
            'gross_amount' => 25,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 5,
        ]);

        $receivable = $this->createReceivableForOrderUuid(
            orderUuid: $order['uuid'],
            grossAmount: 25,
            platformFeeAmount: 20,
            netAmount: 5,
            status: 'released',
            settlement: $settlement,
        );

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/refunds", [
            'type' => 'total',
            'amount' => 12,
            'reason' => 'Estorno apos repasse',
            'refunded_at' => '2026-08-03',
        ])->assertStatus(201);

        $receivable->refresh();
        $settlement->refresh();

        $organizerAdjustment = SettlementAdjustment::query()
            ->where('receivable_id', $receivable->id)
            ->where('type', 'refund_organizer_deduction')
            ->firstOrFail();

        $platformExposure = SettlementAdjustment::query()
            ->where('receivable_id', $receivable->id)
            ->where('type', 'refund_platform_exposure')
            ->firstOrFail();

        $this->assertSame('pending_recovery', $organizerAdjustment->status);
        $this->assertSame('5.00', $organizerAdjustment->amount);
        $this->assertSame('pending_review', $platformExposure->status);
        $this->assertSame('7.00', $platformExposure->amount);
        $this->assertSame('5.00', $receivable->net_amount);
        $this->assertSame('5.00', $settlement->net_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'settlement_adjustment_id' => $organizerAdjustment->id,
            'entry_type' => 'refund_recovery_pending',
            'amount' => 5,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'settlement_adjustment_id' => $platformExposure->id,
            'entry_type' => 'refund_platform_exposure',
            'amount' => 7,
        ]);
    }
}
