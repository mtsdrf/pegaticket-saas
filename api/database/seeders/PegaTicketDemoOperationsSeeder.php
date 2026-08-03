<?php

namespace Database\Seeders;

use App\DTOs\Ticket\CheckinTicketDTO;
use App\Models\Event\Event;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use App\Models\Ticket\Ticket;
use App\Models\User\User;
use App\Models\Venue\Seat;
use App\Services\Ticket\CheckinService;
use App\Services\Ticket\TicketIssuanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PegaTicketDemoOperationsSeeder extends Seeder
{
    private const TENANT_SLUG = 'demo-showcase';
    private const OPERATOR_EMAIL = 'demo@pegaticket.com';

    public function __construct(
        private readonly TicketIssuanceService $ticketIssuanceService = new TicketIssuanceService(),
        private readonly CheckinService $checkinService = new CheckinService(),
    ) {
    }

    public function run(): void
    {
        Config::set('mail.default', 'array');

        DB::transaction(function () {
            $tenant = Tenant::query()->where('slug', self::TENANT_SLUG)->firstOrFail();
            $operator = User::query()->where('email', self::OPERATOR_EMAIL)->firstOrFail();

            $festival = Event::query()->where('tenant_id', $tenant->id)->where('slug', 'festival-pegaticket-2026')->firstOrFail();
            $summit = Event::query()->where('tenant_id', $tenant->id)->where('slug', 'summit-produtores-2026')->firstOrFail();
            $lounge = Event::query()->where('tenant_id', $tenant->id)->where('slug', 'noite-premium-lounge')->firstOrFail();
            $theater = Event::query()->where('tenant_id', $tenant->id)->where('slug', 'teatro-das-estrelas')->firstOrFail();
            $arena = Event::query()->where('tenant_id', $tenant->id)->where('slug', 'arena-experience-cup')->firstOrFail();

            $camila = $this->upsertCustomer($tenant, [
                'email' => 'camila.demo@pegaticket.com',
                'name' => 'Camila',
                'last_name' => 'Ramos',
                'cpf_cnpj' => '12345678901',
                'phone_primary' => '11950000001',
                'notes' => 'Cliente demo com compra manual e historico de ingressos.',
            ]);

            $rafael = $this->upsertCustomer($tenant, [
                'email' => 'rafael.demo@pegaticket.com',
                'name' => 'Rafael',
                'last_name' => 'Almeida',
                'cpf_cnpj' => '12345678902',
                'phone_primary' => '11950000002',
                'notes' => 'Cliente demo de venda online paga com check-in.',
            ]);

            $laura = $this->upsertCustomer($tenant, [
                'email' => 'laura.demo@pegaticket.com',
                'name' => 'Laura',
                'last_name' => 'Moraes',
                'cpf_cnpj' => '12345678903',
                'phone_primary' => '11950000003',
                'notes' => 'Cliente demo com compra aguardando aprovacao.',
            ]);

            $bruno = $this->upsertCustomer($tenant, [
                'email' => 'bruno.demo@pegaticket.com',
                'name' => 'Bruno',
                'last_name' => 'Santos',
                'cpf_cnpj' => '12345678904',
                'phone_primary' => '11950000004',
                'notes' => 'Cliente demo com cobranca Pix pendente.',
            ]);

            $renata = $this->upsertCustomer($tenant, [
                'email' => 'renata.demo@pegaticket.com',
                'name' => 'Renata',
                'last_name' => 'Souza',
                'cpf_cnpj' => '12345678905',
                'phone_primary' => '11950000005',
                'notes' => 'Cliente demo com venda cancelada e ingresso estornado.',
            ]);

            $festivalTicket = TicketType::query()->where('event_id', $festival->id)->where('name', 'Pista inteira')->firstOrFail();
            $festivalBatch = TicketBatch::query()->where('ticket_type_id', $festivalTicket->id)->where('name', 'Segundo lote')->firstOrFail();
            $festivalParking = EventProduct::query()->where('event_id', $festival->id)->where('name', 'Estacionamento oficial')->firstOrFail();

            $summitTicket = TicketType::query()->where('event_id', $summit->id)->where('name', 'Inscricao standard')->firstOrFail();

            $loungeTicket = TicketType::query()->where('event_id', $lounge->id)->where('name', 'Reserva de mesa')->firstOrFail();
            $loungeBatch = TicketBatch::query()->where('ticket_type_id', $loungeTicket->id)->where('name', 'Lote unico')->firstOrFail();
            $loungeSeat = Seat::query()->where('tenant_id', $tenant->id)->where('label', 'Mesa A01')->firstOrFail();

            $theaterTicket = TicketType::query()->where('event_id', $theater->id)->where('name', 'Plateia inteira')->firstOrFail();
            $theaterBatch = TicketBatch::query()->where('ticket_type_id', $theaterTicket->id)->where('name', 'Lote principal')->firstOrFail();
            $theaterSeatA01 = Seat::query()->where('tenant_id', $tenant->id)->where('label', 'A01')->firstOrFail();
            $theaterSeatA02 = Seat::query()->where('tenant_id', $tenant->id)->where('label', 'A02')->firstOrFail();

            $arenaPremium = TicketType::query()->where('event_id', $arena->id)->where('name', 'Premium numerado')->firstOrFail();
            $arenaPremiumBatch = TicketBatch::query()->where('ticket_type_id', $arenaPremium->id)->where('name', 'Lote premium')->firstOrFail();
            $arenaSeatP1 = Seat::query()->where('tenant_id', $tenant->id)->where('label', 'P1')->firstOrFail();

            $manualSale = $this->upsertSale(
                tenant: $tenant,
                customer: $camila['customer'],
                operator: $operator,
                clientSaleUuid: 'demo-festival-paid',
                attributes: [
                    'origin' => 'staff',
                    'status' => 'confirmed',
                    'payment_method' => 'cash',
                    'total_amount' => 275.00,
                    'paid_amount' => 275.00,
                    'is_paid' => true,
                    'paid_at' => CarbonImmutable::now()->subDays(4)->setTime(20, 15),
                    'notes' => 'Venda manual demonstrativa com ingresso e estacionamento.',
                ],
                items: [
                    [
                        'ticket_type_id' => $festivalTicket->id,
                        'ticket_batch_id' => $festivalBatch->id,
                        'quantity' => 2,
                        'unit_price' => 120.00,
                        'line_total' => 240.00,
                        'attendee_data' => [
                            ['name' => 'Camila Ramos', 'document' => '12345678901'],
                            ['name' => 'Paula Ramos', 'document' => '12345678906'],
                        ],
                    ],
                    [
                        'event_product_id' => $festivalParking->id,
                        'quantity' => 1,
                        'unit_price' => 35.00,
                        'line_total' => 35.00,
                        'notes' => 'Placa ABC1D23',
                    ],
                ],
                payments: [
                    [
                        'provider' => 'manual',
                        'method' => 'cash',
                        'amount' => 275.00,
                        'status' => 'paid',
                        'paid_at' => CarbonImmutable::now()->subDays(4)->setTime(20, 15),
                        'metadata' => ['demo' => true, 'channel' => 'manual'],
                    ],
                ],
                issueTickets: true,
            );

            $theaterSale = $this->upsertSale(
                tenant: $tenant,
                customer: $rafael['customer'],
                operator: $operator,
                clientSaleUuid: 'demo-theater-paid',
                attributes: [
                    'origin' => 'storefront',
                    'status' => 'confirmed',
                    'payment_method' => 'credit_card',
                    'total_amount' => 320.00,
                    'paid_amount' => 320.00,
                    'is_paid' => true,
                    'paid_at' => CarbonImmutable::now()->subDays(2)->setTime(14, 5),
                    'notes' => 'Venda online paga com assentos marcados e check-in.',
                ],
                items: [
                    [
                        'ticket_type_id' => $theaterTicket->id,
                        'ticket_batch_id' => $theaterBatch->id,
                        'seat_id' => $theaterSeatA01->id,
                        'quantity' => 1,
                        'unit_price' => 160.00,
                        'line_total' => 160.00,
                        'attendee_data' => [
                            ['name' => 'Rafael Almeida', 'document' => '12345678902'],
                        ],
                    ],
                    [
                        'ticket_type_id' => $theaterTicket->id,
                        'ticket_batch_id' => $theaterBatch->id,
                        'seat_id' => $theaterSeatA02->id,
                        'quantity' => 1,
                        'unit_price' => 160.00,
                        'line_total' => 160.00,
                        'attendee_data' => [
                            ['name' => 'Julia Almeida', 'document' => '12345678907'],
                        ],
                    ],
                ],
                payments: [
                    [
                        'provider' => 'pagbank',
                        'method' => 'credit_card',
                        'amount' => 320.00,
                        'status' => 'paid',
                        'paid_at' => CarbonImmutable::now()->subDays(2)->setTime(14, 5),
                        'metadata' => ['demo' => true, 'channel' => 'storefront', 'provider_status' => 'PAID'],
                    ],
                ],
                issueTickets: true,
            );

            $pendingApprovalSale = $this->upsertSale(
                tenant: $tenant,
                customer: $laura['customer'],
                operator: $operator,
                clientSaleUuid: 'demo-summit-pending',
                attributes: [
                    'origin' => 'storefront',
                    'status' => 'pending_approval',
                    'payment_method' => 'credit_card',
                    'total_amount' => 390.00,
                    'paid_amount' => 0.00,
                    'is_paid' => false,
                    'paid_at' => null,
                    'notes' => 'Venda online aguardando aprovacao para demonstrar fila operacional.',
                ],
                items: [
                    [
                        'ticket_type_id' => $summitTicket->id,
                        'quantity' => 1,
                        'unit_price' => 390.00,
                        'line_total' => 390.00,
                        'attendee_data' => [
                            ['name' => 'Laura Moraes', 'document' => '12345678903'],
                        ],
                    ],
                ],
                payments: [],
                issueTickets: false,
            );

            $pendingPixSale = $this->upsertSale(
                tenant: $tenant,
                customer: $bruno['customer'],
                operator: $operator,
                clientSaleUuid: 'demo-arena-pix-pending',
                attributes: [
                    'origin' => 'storefront',
                    'status' => 'confirmed',
                    'payment_method' => 'pix',
                    'total_amount' => 280.00,
                    'paid_amount' => 0.00,
                    'is_paid' => false,
                    'paid_at' => null,
                    'notes' => 'Venda online com Pix pendente para demonstrar conciliacao.',
                ],
                items: [
                    [
                        'ticket_type_id' => $arenaPremium->id,
                        'ticket_batch_id' => $arenaPremiumBatch->id,
                        'seat_id' => $arenaSeatP1->id,
                        'quantity' => 1,
                        'unit_price' => 280.00,
                        'line_total' => 280.00,
                        'attendee_data' => [
                            ['name' => 'Bruno Santos', 'document' => '12345678904'],
                        ],
                    ],
                ],
                payments: [
                    [
                        'provider' => 'pagbank',
                        'method' => 'pix',
                        'amount' => 280.00,
                        'status' => 'pending',
                        'paid_at' => null,
                        'metadata' => ['demo' => true, 'channel' => 'storefront', 'provider_status' => 'WAITING'],
                    ],
                ],
                issueTickets: false,
            );

            $cancelledSale = $this->upsertSale(
                tenant: $tenant,
                customer: $renata['customer'],
                operator: $operator,
                clientSaleUuid: 'demo-lounge-cancelled',
                attributes: [
                    'origin' => 'storefront',
                    'status' => 'confirmed',
                    'payment_method' => 'debit_card',
                    'total_amount' => 700.00,
                    'paid_amount' => 700.00,
                    'is_paid' => true,
                    'paid_at' => CarbonImmutable::now()->subDay()->setTime(18, 40),
                    'cancelled_at' => CarbonImmutable::now()->subHours(10),
                    'cancellation_reason' => 'Cancelamento demonstrativo para mostrar venda estornada.',
                    'notes' => 'Venda cancelada com ingresso estornado.',
                ],
                items: [
                    [
                        'ticket_type_id' => $loungeTicket->id,
                        'ticket_batch_id' => $loungeBatch->id,
                        'seat_id' => $loungeSeat->id,
                        'quantity' => 1,
                        'unit_price' => 700.00,
                        'line_total' => 700.00,
                        'attendee_data' => [
                            ['name' => 'Renata Souza', 'document' => '12345678905'],
                        ],
                    ],
                ],
                payments: [
                    [
                        'provider' => 'pagbank',
                        'method' => 'debit_card',
                        'amount' => 700.00,
                        'status' => 'refunded',
                        'paid_at' => CarbonImmutable::now()->subDay()->setTime(18, 40),
                        'metadata' => ['demo' => true, 'channel' => 'storefront', 'provider_status' => 'REFUNDED'],
                    ],
                ],
                issueTickets: true,
                cancelTickets: true,
            );

            $this->seedCheckins($tenant, $operator, $theater, $theaterSale, $cancelledSale);

            if ($this->command) {
                $this->command->info('Seeder operacional de demonstracao executado com sucesso.');
                $this->command->line('Vendas criadas/atualizadas: 5');
                $this->command->line('Manual paga: '.$manualSale->uuid);
                $this->command->line('Online paga com check-in: '.$theaterSale->uuid);
                $this->command->line('Online aguardando aprovacao: '.$pendingApprovalSale->uuid);
                $this->command->line('Online Pix pendente: '.$pendingPixSale->uuid);
                $this->command->line('Online cancelada/estornada: '.$cancelledSale->uuid);
            }
        });
    }

    /**
     * @param array<string, string> $data
     * @return array{customer: FinalCustomer, link: FinalCustomerTenantLink}
     */
    private function upsertCustomer(Tenant $tenant, array $data): array
    {
        /** @var FinalCustomer $customer */
        $customer = FinalCustomer::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'last_name' => $data['last_name'],
            ],
        );

        /** @var FinalCustomerTenantLink $link */
        $link = FinalCustomerTenantLink::query()->updateOrCreate(
            ['final_customer_id' => $customer->id, 'tenant_id' => $tenant->id],
            [
                'cpf_cnpj' => $data['cpf_cnpj'],
                'phone_primary' => $data['phone_primary'],
                'phone_secondary' => null,
                'notes' => $data['notes'],
                'is_trusted' => true,
                'is_active' => true,
                'confirmed_at' => CarbonImmutable::now()->subDays(15),
            ],
        );

        return ['customer' => $customer, 'link' => $link];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $payments
     */
    private function upsertSale(
        Tenant $tenant,
        FinalCustomer $customer,
        User $operator,
        string $clientSaleUuid,
        array $attributes,
        array $items,
        array $payments,
        bool $issueTickets,
        bool $cancelTickets = false,
    ): Sale {
        /** @var Sale $sale */
        $sale = Sale::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('client_sale_uuid', $clientSaleUuid)
            ->first() ?? new Sale();

        if ($sale->trashed()) {
            $sale->restore();
        }

        $sale->fill([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'is_installment' => false,
            'service_fee' => 0,
            'discount_amount' => 0,
            'needs_change' => false,
            'change_for_amount' => null,
            'operated_by' => $operator->id,
            'client_sale_uuid' => $clientSaleUuid,
            'due_date' => CarbonImmutable::now()->addDays(2)->toDateString(),
        ] + $attributes);
        $sale->save();

        $this->resetSaleArtifacts($sale);
        $this->rebuildSaleItems($sale, $items);
        $this->rebuildPayments($sale, $payments);

        if ($issueTickets) {
            $this->ticketIssuanceService->issueForSale($sale->fresh(), $operator->id);
        }

        if ($cancelTickets) {
            $this->ticketIssuanceService->cancelForSale($sale->fresh(), $operator->id);
        }

        return $sale->fresh();
    }

    private function resetSaleArtifacts(Sale $sale): void
    {
        $saleItemIds = DB::table('sale_items')->where('sale_id', $sale->id)->pluck('id');

        if ($saleItemIds->isNotEmpty()) {
            $ticketIds = DB::table('tickets')->whereIn('sale_item_id', $saleItemIds)->pluck('id');

            if ($ticketIds->isNotEmpty()) {
                DB::table('ticket_checkins')->whereIn('ticket_id', $ticketIds)->delete();
                DB::table('tickets')->whereIn('id', $ticketIds)->delete();
            }

            DB::table('sale_items')->whereIn('id', $saleItemIds)->delete();
        }

        DB::table('payments')
            ->where('payable_type', Sale::class)
            ->where('payable_id', $sale->id)
            ->delete();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function rebuildSaleItems(Sale $sale, array $items): void
    {
        foreach ($items as $item) {
            SaleItem::create([
                'tenant_id' => $sale->tenant_id,
                'sale_id' => $sale->id,
                'ticket_type_id' => $item['ticket_type_id'] ?? null,
                'event_product_id' => $item['event_product_id'] ?? null,
                'seat_id' => $item['seat_id'] ?? null,
                'ticket_batch_id' => $item['ticket_batch_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'notes' => $item['notes'] ?? null,
                'attendee_data' => $item['attendee_data'] ?? null,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     */
    private function rebuildPayments(Sale $sale, array $payments): void
    {
        foreach ($payments as $index => $payment) {
            Payment::create([
                'payable_type' => Sale::class,
                'payable_id' => $sale->id,
                'provider' => $payment['provider'],
                'provider_charge_id' => 'demo-'.Str::slug($sale->client_sale_uuid).'-'.($index + 1),
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'status' => $payment['status'],
                'paid_at' => $payment['paid_at'],
                'idempotency_key' => 'demo:'.$sale->client_sale_uuid.':'.($index + 1),
                'metadata' => $payment['metadata'] ?? ['demo' => true],
            ]);
        }
    }

    private function seedCheckins(Tenant $tenant, User $operator, Event $theater, Sale $theaterSale, Sale $cancelledSale): void
    {
        $checkedTicket = Ticket::query()
            ->whereHas('saleItem.sale', fn ($query) => $query->where('id', $theaterSale->id))
            ->orderBy('id')
            ->firstOrFail();

        $this->checkinService->checkin(
            $tenant->id,
            CheckinTicketDTO::fromArray([
                'code' => $checkedTicket->code,
                'event_uuid' => $theater->uuid,
                'gate_name' => 'Portaria Principal',
                'device_info' => 'Scanner demo principal',
            ]),
            $operator->id,
        );

        $this->checkinService->checkin(
            $tenant->id,
            CheckinTicketDTO::fromArray([
                'code' => $checkedTicket->code,
                'event_uuid' => $theater->uuid,
                'gate_name' => 'Portaria Principal',
                'reason' => 'tentativa duplicada',
                'device_info' => 'Scanner demo principal',
            ]),
            $operator->id,
        );

        $cancelledTicket = Ticket::query()
            ->whereHas('saleItem.sale', fn ($query) => $query->where('id', $cancelledSale->id))
            ->orderBy('id')
            ->firstOrFail();

        $this->checkinService->checkin(
            $tenant->id,
            CheckinTicketDTO::fromArray([
                'code' => $cancelledTicket->code,
                'gate_name' => 'Lounge VIP',
                'reason' => 'ingresso estornado',
                'device_info' => 'Scanner demo lounge',
            ]),
            $operator->id,
        );
    }
}
