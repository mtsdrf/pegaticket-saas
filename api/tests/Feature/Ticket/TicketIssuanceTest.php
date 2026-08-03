<?php

namespace Tests\Feature\Ticket;

use App\Events\Sale\SaleCancelled;
use App\Models\Event\EventProduct;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Services\Ticket\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Emissão automática de Ticket a partir do ciclo de vida da Sale (spec
 * 5.15/roadmap 2.8). SalePaid é disparado tanto por SaleService quanto por
 * SalePaymentService (webhook/Pix) — ambos convergem no mesmo listener
 * (IssueTicketsOnSalePaid), então basta cobrir via o caminho HTTP de
 * criação com mark_as_paid=true (SaleService::performPayment()) para
 * validar a integração ponta a ponta.
 */
class TicketIssuanceTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('ticket-issuance-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'cancel');
        $this->grantPermission('tickets', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function paying_a_sale_issues_one_ticket_per_unit_of_quantity_for_ticket_type_items(): void
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 50]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(201)->json('data');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');

        $this->assertSame(3, Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $saleId))->count());
        $this->assertSame(3, Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $saleId))->where('status', 'ativo')->count());

        $list = $this->auth()->getJson('/api/v1/tickets?sale_uuid=' . $order['uuid'])->assertStatus(200)->json('data');
        $this->assertCount(3, $list);
    }

    #[Test]
    public function does_not_create_a_ticket_for_an_event_product_line(): void
    {
        // EventProduct (adicional/estacionamento) não emite Ticket — só
        // ticket_type_id preenchido gera ingresso (ver
        // TicketIssuanceService::issueForSale()).
        $client = $this->createClient($this->tenant->id);
        $ticketType = $this->createProduct($this->tenant->id, ['price' => 20]);

        $eventProduct = EventProduct::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $ticketType->event_id,
            'name' => 'Estacionamento',
            'price' => 10,
            'kind' => 'parking',
            'status' => 'ativo',
        ]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ['event_product_uuid' => $eventProduct->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201)->json('data');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');

        // Só o item de ticket_type gera Ticket (1), o item de EventProduct
        // (quantidade 2) não gera nenhum.
        $this->assertSame(1, Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $saleId))->count());
    }

    #[Test]
    public function reprocessing_the_sale_paid_event_does_not_duplicate_tickets(): void
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 15]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201)->json('data');

        $orderModel = Sale::where('uuid', $order['uuid'])->first();
        $this->assertSame(2, Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $orderModel->id))->count());

        // Simula reprocessamento de webhook: mesmo evento disparado de novo.
        app(TicketIssuanceService::class)->issueForSale($orderModel, $this->userId);
        app(TicketIssuanceService::class)->issueForSale($orderModel, $this->userId);

        $this->assertSame(2, Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $orderModel->id))->count());
    }

    #[Test]
    public function cancelling_an_unpaid_sale_marks_its_tickets_as_cancelled(): void
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        $orderModel = Sale::where('uuid', $order['uuid'])->first();

        // Sale::cancel() bloqueia cancelamento de venda pago sem cobrança
        // Pix associada (regra de negócio existente, ver
        // SaleService::cancel()) — para exercitar o espelhamento de status
        // em Ticket sem depender do fluxo completo de Pix/webhook, o teste
        // simula o desfecho de is_paid=false diretamente e dispara o
        // mesmo evento que SaleService::cancel() dispara.
        $orderModel->is_paid = false;
        $orderModel->cancelled_at = now();
        $orderModel->save();

        event(new SaleCancelled(
            saleId: $orderModel->id,
            saleUuid: $orderModel->uuid,
            fromStage: 'confirmed',
            toStage: 'cancelled',
            cancellationReason: 'teste',
            actorId: $this->userId,
        ));

        $ticket = Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $orderModel->id))->firstOrFail();
        $this->assertSame('cancelado', $ticket->status);
    }

    #[Test]
    public function cancelling_a_paid_sale_marks_its_tickets_as_estornado(): void
    {
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 30]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        $orderModel = Sale::where('uuid', $order['uuid'])->first();
        $this->assertTrue($orderModel->is_paid);

        $orderModel->cancelled_at = now();
        $orderModel->save();

        event(new SaleCancelled(
            saleId: $orderModel->id,
            saleUuid: $orderModel->uuid,
            fromStage: 'confirmed',
            toStage: 'cancelled',
            cancellationReason: 'estorno de teste',
            actorId: $this->userId,
        ));

        $ticket = Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $orderModel->id))->firstOrFail();
        $this->assertSame('estornado', $ticket->status);
    }
}
