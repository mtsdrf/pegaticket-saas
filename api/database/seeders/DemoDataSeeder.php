<?php

namespace Database\Seeders;

use App\DTOs\Affiliate\CreateAffiliateDTO;
use App\DTOs\Sale\CreateSaleDTO;
use App\DTOs\Sale\CreateSaleRefundDTO;
use App\DTOs\Storefront\CreateCouponDTO;
use App\DTOs\Ticket\CheckinTicketDTO;
use App\DTOs\Ticket\CreateTicketResaleListingDTO;
use App\DTOs\TicketTypeWaitlist\CreateTicketTypeWaitlistEntryDTO;
use App\Models\Affiliate\Affiliate;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventGate;
use App\Models\Event\EventSession;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Sale\Sale;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\StorefrontFunnelEvent;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use App\Models\Ticket\Ticket;
use App\Models\User\User;
use App\Services\Affiliate\AffiliateService;
use App\Services\Sale\SalePaymentService;
use App\Services\Sale\SaleRefundService;
use App\Services\Sale\SaleService;
use App\Services\Storefront\CouponService;
use App\Services\Tenant\TenantProvisioningService;
use App\Services\Ticket\CheckinService;
use App\Services\Ticket\TicketResaleService;
use App\Services\TicketTypeWaitlist\TicketTypeWaitlistService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Massa de dados de demonstração (volume) para o tenant `demo-showcase`
 * (usuário `demo@pegaticket.com`), em cima da base estrutural já criada por
 * `PegaTicketDemoSeeder`/`PegaTicketDemoOperationsSeeder` (tenant, eventos,
 * mapas, 5 vendas de exemplo). Este seeder soma volume real — centenas de
 * vendas distribuídas no tempo, clientes com histórico variado, afiliados,
 * cupons, check-ins, revenda, fila de espera e funil — para os
 * dashboards/relatórios novos mostrarem dado de verdade.
 *
 * Passa pelos Services de domínio reais (SaleService/SalePaymentService/
 * CheckinService/AffiliateService/CouponService/TicketResaleService/
 * TicketTypeWaitlistService), nunca insert direto de venda — os
 * Events/Listeners (emissão de ticket, comissão de afiliado, motor de
 * risco, recebível) disparam como no fluxo real. Idempotente: todo dado
 * criado aqui é marcado (client_sale_uuid `bulk-*`, e-mail
 *
 * `*@bulkdemo.pegaticket.com`, tracking_code `BULK-*`, cupom `BULK*`) e
 * limpo no início de cada execução antes de recriar, para não duplicar
 * nem contaminar as vendas de exemplo do seeder estrutural.
 */
class DemoDataSeeder extends Seeder
{
    private const TENANT_SLUG = 'demo-showcase';

    private const OPERATOR_EMAIL = 'demo@pegaticket.com';

    private const BULK_EMAIL_DOMAIN = '@bulkdemo.pegaticket.com';

    private const BULK_SALE_PREFIX = 'bulk-';

    private Tenant $tenant;

    private User $operator;

    /** @var User[] */
    private array $staffOperators = [];

    public function __construct(
        private readonly SaleService $saleService,
        private readonly SalePaymentService $salePaymentService,
        private readonly SaleRefundService $saleRefundService,
        private readonly CheckinService $checkinService,
        private readonly AffiliateService $affiliateService,
        private readonly CouponService $couponService,
        private readonly TicketResaleService $ticketResaleService,
        private readonly TicketTypeWaitlistService $waitlistService,
    ) {}

    public function run(): void
    {
        Config::set('mail.default', 'array');

        // Garante a base estrutural (tenant, categorias, mapas, 5 eventos,
        // 5 vendas de exemplo) de forma idempotente antes de somar volume.
        $this->call(PegaTicketDemoSeeder::class);

        $this->tenant = Tenant::query()->where('slug', self::TENANT_SLUG)->firstOrFail();
        $this->operator = User::query()->where('email', self::OPERATOR_EMAIL)->firstOrFail();

        app()->instance('tenant_id', $this->tenant->id);
        app()->instance('tenant', $this->tenant);
        // Vários Events/Listeners exigem Auth::id() não-nulo (ex.:
        // CouponCreated/AffiliateCreated/SaleCreated). O seeder não passa
        // por autenticação HTTP, então autentica como o operador demo
        // pelo tempo de execução — mesmo ator que a UI usaria.
        Auth::setUser($this->operator);

        DB::transaction(function () {
            $this->cleanPreviousBulkData();
        });

        $this->staffOperators = $this->createStaffOperators();
        $extraEvents = $this->createExtraEvents();

        $events = Event::query()->where('tenant_id', $this->tenant->id)->get();
        $ticketTypesByEvent = $this->indexSellableTicketTypes($events);

        $customers = $this->createCustomers();
        $affiliates = $this->createAffiliates();
        $coupons = $this->createCoupons();
        $this->createGates($events);

        $sales = $this->generateBulkSales($ticketTypesByEvent, $customers, $affiliates, $coupons);
        $this->triggerRiskHeuristics($ticketTypesByEvent, $customers);
        $this->generateRefunds($sales, $customers);
        $this->generateCheckins($events);
        $this->generateResaleAndTransfers($customers);
        $this->generateWaitlist($ticketTypesByEvent);
        $this->generateFunnelEvents($events);
        $this->generateCouponAbusePattern($coupons, $customers, $ticketTypesByEvent);

        $this->printSummary(count($sales), count($customers), count($affiliates), count($coupons), count($extraEvents));
    }

    // -------------------------------------------------------------------
    // Limpeza idempotente
    // -------------------------------------------------------------------

    private function cleanPreviousBulkData(): void
    {
        $tenantId = $this->tenant->id;

        $saleIds = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('client_sale_uuid', 'like', self::BULK_SALE_PREFIX.'%')
            ->pluck('id');

        if ($saleIds->isNotEmpty()) {
            $saleItemIds = DB::table('sale_items')->whereIn('sale_id', $saleIds)->pluck('id');
            $ticketIds = DB::table('tickets')->whereIn('sale_item_id', $saleItemIds)->pluck('id');

            if ($ticketIds->isNotEmpty()) {
                DB::table('ticket_checkins')->whereIn('ticket_id', $ticketIds)->delete();

                $resaleListingIds = DB::table('ticket_resale_listings')->whereIn('ticket_id', $ticketIds)->pluck('id');
                if ($resaleListingIds->isNotEmpty()) {
                    DB::table('payments')->where('payable_type', 'App\\Models\\Ticket\\TicketResaleListing')->whereIn('payable_id', $resaleListingIds)->delete();
                    DB::table('ticket_resale_listings')->whereIn('id', $resaleListingIds)->delete();
                }

                DB::table('sale_refund_tickets')->whereIn('ticket_id', $ticketIds)->delete();
                DB::table('tickets')->whereIn('id', $ticketIds)->delete();
            }

            DB::table('sale_refunds')->whereIn('sale_id', $saleIds)->delete();
            DB::table('receivables')->whereIn('sale_id', $saleIds)->delete();
            DB::table('coupon_redemptions')->whereIn('sale_id', $saleIds)->delete();
            DB::table('affiliate_commissions')->whereIn('sale_id', $saleIds)->delete();
            DB::table('sale_installments')->whereIn('sale_id', $saleIds)->delete();
            DB::table('payments')->where('payable_type', Sale::class)->whereIn('payable_id', $saleIds)->delete();
            DB::table('sale_items')->whereIn('id', $saleItemIds)->delete();
            DB::table('sales')->whereIn('id', $saleIds)->delete();
        }

        $customerIds = DB::table('final_customers')->where('email', 'like', '%'.self::BULK_EMAIL_DOMAIN)->pluck('id');
        if ($customerIds->isNotEmpty()) {
            DB::table('final_customer_tenant_links')->where('tenant_id', $tenantId)->whereIn('final_customer_id', $customerIds)->delete();
            DB::table('final_customers')->whereIn('id', $customerIds)->delete();
        }

        DB::table('ticket_type_waitlist_entries')->where('tenant_id', $tenantId)->where('email', 'like', '%'.self::BULK_EMAIL_DOMAIN)->delete();
        DB::table('storefront_funnel_events')->where('tenant_id', $tenantId)->where('session_id', 'like', 'bulk-session-%')->delete();
        DB::table('affiliates')->where('tenant_id', $tenantId)->where('tracking_code', 'like', 'BULK-%')->delete();
        DB::table('coupons')->where('tenant_id', $tenantId)->where('code', 'like', 'BULK%')->delete();
    }

    // -------------------------------------------------------------------
    // Estrutura extra (operadores, eventos, clientes, afiliados, cupons)
    // -------------------------------------------------------------------

    /**
     * @return User[]
     */
    private function createStaffOperators(): array
    {
        $provisioning = app(TenantProvisioningService::class);
        $ownerRole = $provisioning->createOwnerRole($this->tenant);

        $operators = [];
        foreach ([
            ['email' => 'bulkstaff1@bulkdemo.pegaticket.com', 'name' => 'Operador Bilheteria 1'],
            ['email' => 'bulkstaff2@bulkdemo.pegaticket.com', 'name' => 'Operador Bilheteria 2'],
        ] as $data) {
            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make(Str::random(24)), 'is_active' => true],
            );

            $provisioning->attachOwnerUser($this->tenant, $user->id, $ownerRole);
            $operators[] = $user;
        }

        return $operators;
    }

    /**
     * @return Event[]
     */
    private function createExtraEvents(): array
    {
        $category = EventCategory::query()->where('tenant_id', $this->tenant->id)->where('name', 'Shows')->first()
            ?? EventCategory::query()->where('tenant_id', $this->tenant->id)->first();

        $definitions = [
            [
                'slug' => 'feira-gastronomica-inverno',
                'name' => 'Feira Gastronômica de Inverno',
                'type' => 'ingresso',
                'startsAt' => CarbonImmutable::now()->subDays(55)->setTime(11, 0),
                'durationHours' => 9,
                'capacity' => 900,
                'price' => 45.0,
                'sessions' => 1,
            ],
            [
                'slug' => 'workshop-fotografia-urbana',
                'name' => 'Workshop de Fotografia Urbana',
                'type' => 'inscricao',
                'startsAt' => CarbonImmutable::now()->subDays(16)->setTime(9, 0),
                'durationHours' => 6,
                'capacity' => 350,
                'price' => 180.0,
                'sessions' => 1,
            ],
            [
                'slug' => 'reveillon-pegaticket-2027',
                'name' => 'Réveillon PegaTicket 2027',
                'type' => 'ingresso',
                'startsAt' => CarbonImmutable::now()->addDays(78)->setTime(22, 0),
                'durationHours' => 8,
                'capacity' => 1500,
                'price' => 260.0,
                'sessions' => 2,
            ],
        ];

        $events = [];

        foreach ($definitions as $definition) {
            $startsAt = $definition['startsAt'];
            $endsAt = $startsAt->addHours($definition['durationHours']);

            /** @var Event $event */
            $event = Event::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'slug' => $definition['slug']],
                [
                    'event_category_id' => $category->id,
                    'name' => $definition['name'],
                    'description_short' => 'Evento de demonstração gerado pelo seeder de massa de dados.',
                    'description_full' => 'Evento de demonstração gerado pelo seeder de massa de dados para popular relatórios e dashboards com volume real.',
                    'type' => $definition['type'],
                    'location_name' => 'Espaço Showcase Demo',
                    'location_address' => 'Av. Demonstração, 500 - Sao Paulo/SP',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'visibility' => 'public',
                    'status' => 'publicado',
                ],
            );

            $salesStartAt = $startsAt->subDays(70);
            $salesEndAt = $startsAt->subHour();

            if ($definition['sessions'] > 1) {
                for ($i = 1; $i <= $definition['sessions']; $i++) {
                    $sessionStart = $startsAt->addHours(($i - 1) * 2);

                    /** @var EventSession $session */
                    $session = EventSession::query()->updateOrCreate(
                        ['tenant_id' => $this->tenant->id, 'event_id' => $event->id, 'name' => 'Sessão '.$i],
                        [
                            'starts_at' => $sessionStart,
                            'ends_at' => $sessionStart->addHours(2),
                            'gate_opens_at' => $sessionStart->subHour(),
                            'capacity' => (int) ($definition['capacity'] / $definition['sessions']),
                            'status' => 'publicado',
                            'sales_start_at' => $salesStartAt,
                            'sales_end_at' => $sessionStart->subMinutes(30),
                        ],
                    );

                    $this->upsertTicketType($event, $session, 'Ingresso '.$session->name, $definition['price'], (int) ($definition['capacity'] / $definition['sessions']), $salesStartAt, $sessionStart->subMinutes(30), 'BULKSTRUCT-'.strtoupper($definition['slug']).'-'.$i);
                }
            } else {
                $this->upsertTicketType($event, null, 'Ingresso único', $definition['price'], $definition['capacity'], $salesStartAt, $salesEndAt, 'BULKSTRUCT-'.strtoupper($definition['slug']));
            }

            $events[] = $event;
        }

        return $events;
    }

    private function upsertTicketType(Event $event, ?EventSession $session, string $name, float $price, int $quantity, CarbonImmutable $salesStartAt, CarbonImmutable $salesEndAt, string $sku): TicketType
    {
        return TicketType::query()->updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'event_id' => $event->id, 'name' => $name],
            [
                'event_session_id' => $session?->id,
                'description' => 'Ingresso de demonstração.',
                'price' => $price,
                'quantity_available' => $quantity,
                'min_per_order' => 1,
                'max_per_order' => 6,
                'sales_start_at' => $salesStartAt,
                'sales_end_at' => $salesEndAt,
                'status' => 'ativo',
                'sku' => $sku,
                'unit' => 'un',
            ],
        );
    }

    /**
     * Ticket types "sem assento" por evento — usados na geração de volume
     * (bulk sales nunca escolhem seat_id, evita conflito de capacidade de
     * mapa com os dados do seeder estrutural).
     *
     * @return array<int, array{event: Event, ticket_types: TicketType[]}>
     */
    private function indexSellableTicketTypes($events): array
    {
        $indexed = [];

        foreach ($events as $event) {
            $ticketTypes = TicketType::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('event_id', $event->id)
                ->where('status', 'ativo')
                ->get();

            if ($ticketTypes->isEmpty()) {
                continue;
            }

            $indexed[] = ['event' => $event, 'ticket_types' => $ticketTypes->values()->all()];
        }

        return $indexed;
    }

    /**
     * @return array<int, array{customer: FinalCustomer, profile: string}>
     */
    private function createCustomers(): array
    {
        $firstNames = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elaine', 'Felipe', 'Gabriela', 'Hugo', 'Isabela', 'Joao',
            'Karina', 'Lucas', 'Marina', 'Nelson', 'Olivia', 'Pedro', 'Queila', 'Rafael', 'Sabrina', 'Thiago',
            'Ursula', 'Victor', 'Wanessa', 'Xavier', 'Yasmin', 'Zeca', 'Aline', 'Breno', 'Camila', 'Douglas',
            'Estela', 'Fabio', 'Giovanna', 'Henrique', 'Ingrid', 'Julio', 'Katia', 'Leonardo', 'Mariana', 'Nicolas'];
        $lastNames = ['Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Nascimento',
            'Lima', 'Araujo', 'Fernandes', 'Carvalho', 'Gomes', 'Martins', 'Rocha', 'Ribeiro', 'Alves', 'Monteiro', 'Cardoso'];

        $profiles = array_merge(
            array_fill(0, 16, 'novo'),
            array_fill(0, 22, 'recorrente'),
            array_fill(0, 12, 'em_risco'),
            array_fill(0, 10, 'inativo'),
        );

        $customers = [];
        $index = 0;

        foreach ($profiles as $profile) {
            $index++;
            $first = $firstNames[($index * 7) % count($firstNames)];
            $last = $lastNames[($index * 13) % count($lastNames)];
            $email = 'cliente'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).self::BULK_EMAIL_DOMAIN;

            /** @var FinalCustomer $customer */
            $customer = FinalCustomer::query()->create([
                'email' => $email,
                'name' => $first,
                'last_name' => $last,
            ]);

            FinalCustomerTenantLink::query()->create([
                'final_customer_id' => $customer->id,
                'tenant_id' => $this->tenant->id,
                'cpf_cnpj' => str_pad((string) (90000000000 + $index), 11, '0', STR_PAD_LEFT),
                'phone_primary' => '119'.str_pad((string) (60000000 + $index), 8, '0', STR_PAD_LEFT),
                'notes' => 'Cliente de demonstração ('.$profile.') gerado pelo seeder de massa de dados.',
                'is_trusted' => true,
                'is_active' => true,
                'confirmed_at' => CarbonImmutable::now()->subDays(120),
            ]);

            $customers[] = ['customer' => $customer, 'profile' => $profile];
        }

        return $customers;
    }

    /**
     * @return Affiliate[]
     */
    private function createAffiliates(): array
    {
        $definitions = [
            ['name' => 'Ana Marketing Digital', 'tracking_code' => 'BULK-ANA', 'commission_percentage' => 8.0],
            ['name' => 'Clube dos Eventos', 'tracking_code' => 'BULK-CLUBE', 'commission_percentage' => 12.0],
            ['name' => 'Influencer Bia Ramos', 'tracking_code' => 'BULK-BIA', 'commission_percentage' => 15.0],
            ['name' => 'Parceiro Corporativo Prime', 'tracking_code' => 'BULK-PRIME', 'commission_percentage' => 6.5],
        ];

        $affiliates = [];
        foreach ($definitions as $definition) {
            $affiliates[] = $this->affiliateService->create($this->tenant->id, CreateAffiliateDTO::fromArray([
                'name' => $definition['name'],
                'email' => strtolower(str_replace(' ', '.', $definition['name'])).self::BULK_EMAIL_DOMAIN,
                'tracking_code' => $definition['tracking_code'],
                'commission_percentage' => $definition['commission_percentage'],
                'status' => Affiliate::STATUS_ACTIVE,
            ]));
        }

        return $affiliates;
    }

    /**
     * @return Coupon[]
     */
    private function createCoupons(): array
    {
        $definitions = [
            ['code' => 'BULKDEZ10', 'type' => 'percentage', 'value' => 10.0],
            ['code' => 'BULKPROMO20', 'type' => 'percentage', 'value' => 20.0],
            ['code' => 'BULKFIXO15', 'type' => 'fixed', 'value' => 15.0],
            // Cupom-alvo do sinal de abuso (usage_count>=5 e
            // avg_uses_per_customer>=2.0) — ver generateCouponAbusePattern().
            ['code' => 'BULKABUSE', 'type' => 'percentage', 'value' => 25.0],
        ];

        $coupons = [];
        foreach ($definitions as $definition) {
            $coupons[] = $this->couponService->create($this->tenant->id, CreateCouponDTO::fromArray([
                'code' => $definition['code'],
                'type' => $definition['type'],
                'value' => $definition['value'],
                'is_active' => true,
                'max_uses_total' => 500,
                'max_uses_per_customer' => 10,
            ]));
        }

        return $coupons;
    }

    private function createGates($events): void
    {
        $sample = collect($events)->take(2);

        foreach ($sample as $event) {
            foreach (['Portaria Principal', 'Portaria VIP'] as $gateName) {
                EventGate::query()->updateOrCreate(
                    ['tenant_id' => $this->tenant->id, 'event_id' => $event->id, 'name' => $gateName],
                    ['is_active' => true],
                );
            }
        }
    }

    // -------------------------------------------------------------------
    // Volume de vendas
    // -------------------------------------------------------------------

    /**
     * @param  array<int, array{event: Event, ticket_types: TicketType[]}>  $ticketTypesByEvent
     * @param  array<int, array{customer: FinalCustomer, profile: string}>  $customers
     * @param  Affiliate[]  $affiliates
     * @param  Coupon[]  $coupons
     * @return Sale[]
     */
    private function generateBulkSales(array $ticketTypesByEvent, array $customers, array $affiliates, array $coupons): array
    {
        if (empty($ticketTypesByEvent)) {
            return [];
        }

        $sales = [];
        $saleIndex = 0;
        $now = CarbonImmutable::now();

        foreach ($ticketTypesByEvent as $entry) {
            /** @var Event $event */
            $event = $entry['event'];
            $ticketTypes = $entry['ticket_types'];
            $anchor = CarbonImmutable::parse($event->starts_at);

            // Mais vendas para eventos futuros/recentes (ainda vendendo ou
            // vendidos recentemente), volume moderado para os mais distantes.
            $salesForEvent = $anchor->isFuture() ? random_int(35, 55) : random_int(20, 40);

            for ($i = 0; $i < $salesForEvent; $i++) {
                $saleIndex++;

                $customerEntry = $customers[array_rand($customers)];
                $customer = $customerEntry['customer'];
                $ticketType = $ticketTypes[array_rand($ticketTypes)];

                // Distribuição concentrada perto da data do evento: janela
                // de venda vai de 70 dias antes até a data do evento (ou
                // "agora", o que vier primeiro), com viés quadrático para o
                // fim da janela (mais perto do evento = mais vendas).
                $windowEnd = $anchor->isFuture() ? $now : $anchor->subHours(2);
                $windowStart = $anchor->subDays(70);
                if ($windowStart->greaterThan($windowEnd)) {
                    $windowStart = $windowEnd->subDays(1);
                }
                $totalMinutes = max(60, $windowStart->diffInMinutes($windowEnd));
                $bias = (random_int(0, 1000) / 1000) ** 2;
                $minutesFromStart = (int) ($totalMinutes * $bias);
                $saleDate = $windowStart->addMinutes($minutesFromStart)->addMinutes(random_int(0, 600));
                if ($saleDate->greaterThan($now)) {
                    $saleDate = $now->subMinutes(random_int(1, 500));
                }

                $originRoll = random_int(1, 100);
                $origin = $originRoll <= 55 ? 'storefront' : 'staff';

                $statusRoll = random_int(1, 100);
                $affiliate = null;
                $coupon = null;

                if ($origin === 'storefront' && random_int(1, 100) <= 18) {
                    $affiliate = $affiliates[array_rand($affiliates)];
                }
                if ($origin === 'storefront' && random_int(1, 100) <= 15) {
                    // O cupom de abuso é reservado para generateCouponAbusePattern().
                    $regularCoupons = array_values(array_filter($coupons, fn (Coupon $c) => $c->code !== 'BULKABUSE'));
                    $coupon = $regularCoupons[array_rand($regularCoupons)];
                }

                $quantity = random_int(1, 3);
                $unitPrice = (float) $ticketType->price;
                $discount = $coupon ? $this->estimateDiscount($coupon, $unitPrice * $quantity) : 0.0;

                $attendees = [];
                for ($a = 0; $a < $quantity; $a++) {
                    $attendees[] = ['name' => $customer->name.' Convidado '.($a + 1), 'document' => str_pad((string) random_int(10000000000, 99999999999), 11, '0', STR_PAD_LEFT)];
                }

                $items = [[
                    'ticket_type_uuid' => $ticketType->uuid,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'attendee_data' => $attendees,
                ]];

                if ($statusRoll <= 84) {
                    $status = 'confirmed';
                    $shouldPay = true;
                } elseif ($statusRoll <= 91) {
                    $status = 'pending_approval';
                    $shouldPay = false;
                } elseif ($statusRoll <= 97) {
                    $status = 'confirmed';
                    $shouldPay = false; // Pix pendente de confirmação.
                } else {
                    $status = 'rejected';
                    $shouldPay = false;
                }

                try {
                    $sale = $this->saleService->create(new CreateSaleDTO(
                        tenantId: $this->tenant->id,
                        finalCustomerUuid: $customer->uuid,
                        isInstallment: false,
                        installmentsCount: null,
                        notes: 'Venda de demonstração gerada pelo seeder de massa de dados.',
                        items: $items,
                        origin: $origin,
                        status: $status,
                        couponId: $coupon?->id,
                        discountAmount: $discount,
                        paymentMethod: $origin === 'storefront' ? ['pix', 'credit_card', 'debit_card'][array_rand(['pix', 'credit_card', 'debit_card'])] : 'cash',
                        affiliateId: $affiliate?->id,
                        purchaserIp: $origin === 'storefront' ? '200.150.'.random_int(0, 255).'.'.random_int(1, 254) : null,
                    ));
                } catch (Throwable $e) {
                    continue;
                }

                DB::table('sales')->where('id', $sale->id)->update(['client_sale_uuid' => self::BULK_SALE_PREFIX.str_pad((string) $saleIndex, 5, '0', STR_PAD_LEFT)]);

                if ($coupon !== null) {
                    DB::table('coupon_redemptions')->insert([
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $this->tenant->id,
                        'coupon_id' => $coupon->id,
                        'final_customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'redeemed_at' => $saleDate,
                        'created_at' => $saleDate,
                    ]);
                }

                if ($origin === 'staff') {
                    // create() já paga automaticamente vendas staff não
                    // parceladas — nada a fazer aqui além do backdating.
                } elseif ($shouldPay) {
                    $this->payStorefrontSale($sale, $saleDate);
                } elseif ($status === 'confirmed') {
                    // Pix pendente: cria a cobrança pendente sem conciliar.
                    Payment::query()->create([
                        'payable_type' => Sale::class,
                        'payable_id' => $sale->id,
                        'provider' => 'pagbank',
                        'provider_charge_id' => 'demo-'.$sale->uuid,
                        'method' => 'pix',
                        'amount' => number_format((float) $sale->total_amount, 2, '.', ''),
                        'status' => 'pending',
                        'idempotency_key' => 'demo:'.$sale->uuid,
                        'metadata' => ['demo' => true, 'channel' => 'storefront'],
                    ]);
                }

                $sale->refresh();

                $paidAt = $sale->is_paid ? $saleDate : null;
                DB::table('sales')->where('id', $sale->id)->update([
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                    'paid_at' => $paidAt,
                ]);
                if ($coupon !== null) {
                    DB::table('coupon_redemptions')->where('sale_id', $sale->id)->update(['redeemed_at' => $saleDate, 'created_at' => $saleDate]);
                }
                if ($affiliate !== null) {
                    DB::table('affiliate_commissions')->where('sale_id', $sale->id)->update(['created_at' => $saleDate, 'updated_at' => $saleDate]);
                }
                DB::table('tickets')->whereIn('sale_item_id', DB::table('sale_items')->where('sale_id', $sale->id)->pluck('id'))->update(['created_at' => $saleDate, 'updated_at' => $saleDate]);

                $sales[] = $sale->fresh();
            }
        }

        return $sales;
    }

    private function estimateDiscount(Coupon $coupon, float $subtotal): float
    {
        if ($coupon->type === 'percentage') {
            return round($subtotal * ((float) $coupon->value / 100), 2);
        }

        return min((float) $coupon->value, $subtotal);
    }

    private function payStorefrontSale(Sale $sale, CarbonImmutable $when): void
    {
        $payment = Payment::query()->create([
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'provider' => 'pagbank',
            'provider_charge_id' => 'demo-'.$sale->uuid,
            'method' => $sale->payment_method ?? 'credit_card',
            'amount' => number_format((float) $sale->total_amount, 2, '.', ''),
            'status' => 'pending',
            'idempotency_key' => 'demo:'.$sale->uuid,
            'metadata' => ['demo' => true, 'channel' => 'storefront', 'provider_status' => 'PAID'],
        ]);

        $this->salePaymentService->reconcileWebhookPayment($payment, (float) $sale->total_amount);
    }

    // -------------------------------------------------------------------
    // Antifraude (heurística real, sem forçar o flag)
    // -------------------------------------------------------------------

    /**
     * @param  array<int, array{event: Event, ticket_types: TicketType[]}>  $ticketTypesByEvent
     * @param  array<int, array{customer: FinalCustomer, profile: string}>  $customers
     */
    private function triggerRiskHeuristics(array $ticketTypesByEvent, array $customers): void
    {
        if (empty($ticketTypesByEvent)) {
            return;
        }

        // Heurística 1 (compras repetidas): mesmo cliente, mesmo evento,
        // 3+ vendas pagas em até 6h — sem manipular risk_flagged, é o
        // motor real (FlagRiskOnSalePaid) quem decide.
        $entry = $ticketTypesByEvent[array_rand($ticketTypesByEvent)];
        $ticketType = $entry['ticket_types'][0];
        $riskyCustomer = $customers[array_rand($customers)]['customer'];

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->saleService->create(new CreateSaleDTO(
                    tenantId: $this->tenant->id,
                    finalCustomerUuid: $riskyCustomer->uuid,
                    isInstallment: false,
                    installmentsCount: null,
                    notes: 'Venda de demonstração (padrão antifraude real) gerada pelo seeder.',
                    items: [[
                        'ticket_type_uuid' => $ticketType->uuid,
                        'quantity' => 1,
                        'unit_price' => (float) $ticketType->price,
                        'attendee_data' => [['name' => $riskyCustomer->name, 'document' => '12312312312']],
                    ]],
                    origin: 'staff',
                    status: 'confirmed',
                ));
            } catch (Throwable $e) {
                // Segue mesmo se uma repetição falhar — heurística ainda
                // dispara com 2 de 3, ou tentamos a próxima tenant.
            }
        }
    }

    // -------------------------------------------------------------------
    // Reembolsos
    // -------------------------------------------------------------------

    /**
     * @param  Sale[]  $sales
     * @param  array<int, array{customer: FinalCustomer, profile: string}>  $customers
     */
    private function generateRefunds(array $sales, array $customers): void
    {
        $paidSales = array_values(array_filter($sales, fn (Sale $s) => (bool) $s->is_paid && $s->cancelled_at === null));

        if (empty($paidSales)) {
            return;
        }

        shuffle($paidSales);
        $reasons = [
            'Cliente desistiu do evento',
            'Compra em duplicidade',
            'Erro de cobranca reportado pelo cliente',
            'Evento remarcado, cliente optou por reembolso',
            'Chargeback confirmado pela operadora',
        ];

        $refundTargets = array_slice($paidSales, 0, min(25, count($paidSales)));
        foreach ($refundTargets as $sale) {
            try {
                $this->saleRefundService->create(
                    $sale,
                    new CreateSaleRefundDTO(
                        type: 'total',
                        amount: (float) $sale->total_amount,
                        reason: $reasons[array_rand($reasons)],
                        refundedAt: CarbonImmutable::parse($sale->paid_at ?? $sale->created_at)->addDays(random_int(1, 10))->toDateTimeString(),
                        externalReference: null,
                        notes: 'Reembolso de demonstração gerado pelo seeder.',
                        releaseSeats: false,
                        ticketUuids: null,
                    ),
                    null,
                );
            } catch (Throwable $e) {
                continue;
            }
        }

        // Heurística 6 (abuso de reembolso): mesmo cliente, 3+ estornos em
        // 30 dias — via chamadas reais e sucessivas ao service (dispara o
        // motor de risco de verdade no último estorno).
        $abuseCustomerEntry = $customers[0];
        $abuseSales = array_values(array_filter($paidSales, fn (Sale $s) => $s->final_customer_id === $abuseCustomerEntry['customer']->id));

        if (count($abuseSales) < 3) {
            $extra = array_slice($paidSales, 25, 3 - count($abuseSales));
            $abuseSales = array_merge($abuseSales, $extra);
        }

        foreach (array_slice($abuseSales, 0, 3) as $sale) {
            try {
                $this->saleRefundService->create(
                    $sale,
                    new CreateSaleRefundDTO(
                        type: 'total',
                        amount: (float) $sale->total_amount,
                        reason: 'Reembolso de demonstração (padrão de abuso real)',
                        refundedAt: now()->toDateTimeString(),
                        externalReference: null,
                        notes: 'Reembolso de demonstração gerado pelo seeder (padrão de abuso).',
                        releaseSeats: false,
                        ticketUuids: null,
                    ),
                    null,
                );
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    // -------------------------------------------------------------------
    // Check-ins
    // -------------------------------------------------------------------

    private function generateCheckins($events): void
    {
        $gateNames = ['Portaria Principal', 'Portaria VIP', null];

        foreach ($events as $event) {
            if (CarbonImmutable::parse($event->starts_at)->isFuture() && CarbonImmutable::parse($event->starts_at)->diffInDays(now()) > 3) {
                // Só faz check-in de evento que já começou/está próximo.
                continue;
            }

            $tickets = Ticket::query()
                ->whereHas('saleItem.sale', fn ($q) => $q->where('tenant_id', $this->tenant->id)
                    ->where('client_sale_uuid', 'like', self::BULK_SALE_PREFIX.'%'))
                ->whereHas('saleItem.ticketType', fn ($q) => $q->where('event_id', $event->id))
                ->limit(40)
                ->get();

            foreach ($tickets as $index => $ticket) {
                $operator = $this->staffOperators[$index % max(1, count($this->staffOperators))] ?? $this->operator;
                $gate = $gateNames[$index % count($gateNames)];

                try {
                    $this->checkinService->checkin(
                        $this->tenant->id,
                        CheckinTicketDTO::fromArray([
                            'code' => $ticket->code,
                            'event_uuid' => $event->uuid,
                            'gate_name' => $gate,
                            'device_info' => 'Scanner demo '.($index % 3 + 1),
                        ]),
                        $operator->id,
                    );
                } catch (Throwable $e) {
                    continue;
                }

                // ~15% repete o check-in (gera resultado "ja_utilizado").
                if ($index % 7 === 0) {
                    try {
                        $this->checkinService->checkin(
                            $this->tenant->id,
                            CheckinTicketDTO::fromArray([
                                'code' => $ticket->code,
                                'event_uuid' => $event->uuid,
                                'gate_name' => $gate,
                                'reason' => 'tentativa duplicada demo',
                                'device_info' => 'Scanner demo '.($index % 3 + 1),
                            ]),
                            $operator->id,
                        );
                    } catch (Throwable $e) {
                        continue;
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------
    // Revenda e transferência
    // -------------------------------------------------------------------

    /**
     * @param  array<int, array{customer: FinalCustomer, profile: string}>  $customers
     */
    private function generateResaleAndTransfers(array $customers): void
    {
        $activeTickets = Ticket::query()
            ->whereHas('saleItem.sale', fn ($q) => $q->where('tenant_id', $this->tenant->id)
                ->where('client_sale_uuid', 'like', self::BULK_SALE_PREFIX.'%'))
            ->where('status', 'ativo')
            ->with('saleItem.sale')
            ->limit(10)
            ->get();

        if ($activeTickets->count() < 2) {
            return;
        }

        $buyerCandidate = collect($customers)->firstWhere('customer.id', '!=', $activeTickets->first()->saleItem->sale->final_customer_id);

        foreach ($activeTickets->take(6) as $index => $ticket) {
            $sellerId = $ticket->saleItem->sale->final_customer_id;
            $unitPrice = (float) $ticket->saleItem->unit_price;

            if ($unitPrice <= 0) {
                continue;
            }

            try {
                $listing = $this->ticketResaleService->create(
                    $sellerId,
                    $ticket->uuid,
                    new CreateTicketResaleListingDTO(askingPrice: round($unitPrice * 0.9, 2)),
                );
            } catch (Throwable $e) {
                continue;
            }

            // Metade das listagens é vendida (para popular payout/vendido),
            // metade fica listada em aberto.
            if ($index % 2 === 0 && $buyerCandidate) {
                $buyerId = $buyerCandidate['customer']->id;
                if ($buyerId !== $sellerId) {
                    try {
                        $this->ticketResaleService->purchase($buyerId, $listing->uuid, '11122233344');
                    } catch (Throwable $e) {
                        continue;
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------
    // Fila de espera
    // -------------------------------------------------------------------

    /**
     * @param  array<int, array{event: Event, ticket_types: TicketType[]}>  $ticketTypesByEvent
     */
    private function generateWaitlist(array $ticketTypesByEvent): void
    {
        if (empty($ticketTypesByEvent)) {
            return;
        }

        // Menor capacidade cadastrada = "esgotado" plausível para demo.
        $smallest = null;
        foreach ($ticketTypesByEvent as $entry) {
            foreach ($entry['ticket_types'] as $ticketType) {
                if ($ticketType->quantity_available === null) {
                    continue;
                }
                if ($smallest === null || $ticketType->quantity_available < $smallest->quantity_available) {
                    $smallest = $ticketType;
                }
            }
        }

        if ($smallest === null) {
            return;
        }

        for ($i = 1; $i <= 8; $i++) {
            try {
                $this->waitlistService->create($this->tenant->id, new CreateTicketTypeWaitlistEntryDTO(
                    ticketTypeUuid: $smallest->uuid,
                    name: 'Interessado Fila '.$i,
                    email: 'fila'.$i.self::BULK_EMAIL_DOMAIN,
                    quantityDesired: random_int(1, 2),
                ));
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    // -------------------------------------------------------------------
    // Funil de conversão (log anônimo, sem service dedicado)
    // -------------------------------------------------------------------

    private function generateFunnelEvents($events): void
    {
        $steps = StorefrontFunnelEvent::STEPS;
        $dropoffWeights = [100, 70, 50, 35, 20]; // sessões restantes por etapa (mesmo espírito de funil real)

        $eventsList = collect($events)->values();
        $rows = [];
        $sessionCount = 60;

        for ($s = 1; $s <= $sessionCount; $s++) {
            $sessionId = 'bulk-session-'.str_pad((string) $s, 4, '0', STR_PAD_LEFT);
            $event = $eventsList->isNotEmpty() ? $eventsList->random() : null;
            $createdAt = CarbonImmutable::now()->subDays(random_int(0, 29))->subMinutes(random_int(0, 1440));

            foreach ($steps as $stepIndex => $step) {
                $weight = $dropoffWeights[$stepIndex] ?? 10;
                if (random_int(1, 100) > $weight) {
                    break;
                }

                $rows[] = [
                    'tenant_id' => $this->tenant->id,
                    'event_id' => $event?->id,
                    'session_id' => $sessionId,
                    'step' => $step,
                    'created_at' => $createdAt->addMinutes($stepIndex * 2),
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('storefront_funnel_events')->insert($chunk);
        }
    }

    // -------------------------------------------------------------------
    // Padrão de abuso de cupom
    // -------------------------------------------------------------------

    /**
     * @param  Coupon[]  $coupons
     * @param  array<int, array{customer: FinalCustomer, profile: string}>  $customers
     * @param  array<int, array{event: Event, ticket_types: TicketType[]}>  $ticketTypesByEvent
     */
    private function generateCouponAbusePattern(array $coupons, array $customers, array $ticketTypesByEvent): void
    {
        if (empty($ticketTypesByEvent)) {
            return;
        }

        $abuseCoupon = collect($coupons)->firstWhere('code', 'BULKABUSE');
        if (! $abuseCoupon) {
            return;
        }

        $entry = $ticketTypesByEvent[array_rand($ticketTypesByEvent)];
        $ticketType = $entry['ticket_types'][0];

        // usage_count >= 5 e avg_uses_per_customer >= 2.0: 2 clientes usando
        // o mesmo cupom 3x cada (6 usos, 2 clientes distintos = média 3.0).
        $abusiveCustomers = array_slice($customers, 0, 2);

        foreach ($abusiveCustomers as $customerEntry) {
            $customer = $customerEntry['customer'];

            for ($i = 0; $i < 3; $i++) {
                $unitPrice = (float) $ticketType->price;
                $discount = $this->estimateDiscount($abuseCoupon, $unitPrice);

                try {
                    $sale = $this->saleService->create(new CreateSaleDTO(
                        tenantId: $this->tenant->id,
                        finalCustomerUuid: $customer->uuid,
                        isInstallment: false,
                        installmentsCount: null,
                        notes: 'Venda de demonstração (padrão de abuso de cupom) gerada pelo seeder.',
                        items: [[
                            'ticket_type_uuid' => $ticketType->uuid,
                            'quantity' => 1,
                            'unit_price' => $unitPrice,
                            'attendee_data' => [['name' => $customer->name, 'document' => '32132132132']],
                        ]],
                        origin: 'staff',
                        status: 'confirmed',
                        couponId: $abuseCoupon->id,
                        discountAmount: $discount,
                    ));
                } catch (Throwable $e) {
                    continue;
                }

                $when = CarbonImmutable::now()->subDays(random_int(1, 20))->subHours(random_int(0, 12));
                DB::table('sales')->where('id', $sale->id)->update([
                    'client_sale_uuid' => self::BULK_SALE_PREFIX.'abuse-'.$sale->id,
                    'created_at' => $when,
                    'updated_at' => $when,
                    'paid_at' => $when,
                ]);

                DB::table('coupon_redemptions')->insert([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $this->tenant->id,
                    'coupon_id' => $abuseCoupon->id,
                    'final_customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'redeemed_at' => $when,
                    'created_at' => $when,
                ]);
            }
        }
    }

    // -------------------------------------------------------------------
    // Resumo
    // -------------------------------------------------------------------

    private function printSummary(int $salesCount, int $customersCount, int $affiliatesCount, int $couponsCount, int $extraEventsCount): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Seeder de massa de dados de demonstracao executado com sucesso.');
        $this->command->line('Tenant: '.$this->tenant->name.' ('.$this->tenant->slug.')');
        $this->command->line('Eventos extras criados: '.$extraEventsCount);
        $this->command->line('Vendas de volume geradas: '.$salesCount);
        $this->command->line('Clientes de demonstracao: '.$customersCount);
        $this->command->line('Afiliados: '.$affiliatesCount);
        $this->command->line('Cupons: '.$couponsCount);
    }
}
