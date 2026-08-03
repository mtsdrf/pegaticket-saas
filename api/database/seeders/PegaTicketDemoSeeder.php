<?php

namespace Database\Seeders;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventProduct;
use App\Models\Event\EventSession;
use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSettings;
use App\Models\User\User;
use App\Models\Venue\Seat;
use App\Models\Venue\Venue;
use App\Models\Venue\VenueMapVersion;
use App\Services\Tenant\TenantProvisioningService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PegaTicketDemoSeeder extends Seeder
{
    private const DEMO_EMAIL = 'demo@pegaticket.com';
    private const DEMO_PASSWORD = 'Demo@123456';
    private const TENANT_SLUG = 'demo-showcase';

    private TenantProvisioningService $tenantProvisioningService;

    public function __construct()
    {
        $this->tenantProvisioningService = app(TenantProvisioningService::class);
    }

    public function run(): void
    {
        DB::transaction(function () {
            $plan = Plan::query()->where('slug', 'pegaticket')->firstOrFail();
            $user = $this->upsertDemoUser();
            $tenant = $this->upsertDemoTenant($plan->id);

            $ownerRole = $this->tenantProvisioningService->createOwnerRole($tenant);
            $this->tenantProvisioningService->attachOwnerUser($tenant, $user->id, $ownerRole);
            $this->tenantProvisioningService->syncOwnerRolePermissions($tenant, $ownerRole);

            $this->upsertTenantSettings($tenant);

            $categories = $this->upsertCategories($tenant);

            $loungeMap = $this->upsertVenueMap(
                $tenant,
                'Lounge Premium PegaTicket',
                1600,
                900,
                [
                    ['sector_name' => 'Lounge Central', 'label' => 'Mesa A01', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 220, 'pos_y' => 220],
                    ['sector_name' => 'Lounge Central', 'label' => 'Mesa A02', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 360, 'pos_y' => 220],
                    ['sector_name' => 'Lounge Central', 'label' => 'Mesa A03', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 500, 'pos_y' => 220],
                    ['sector_name' => 'Lounge Central', 'label' => 'Mesa A04', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 640, 'pos_y' => 220],
                    ['sector_name' => 'Varanda VIP', 'label' => 'Mesa B01', 'kind' => 'mesa', 'capacity' => 6, 'pos_x' => 250, 'pos_y' => 470],
                    ['sector_name' => 'Varanda VIP', 'label' => 'Mesa B02', 'kind' => 'mesa', 'capacity' => 6, 'pos_x' => 440, 'pos_y' => 470],
                    ['sector_name' => 'Varanda VIP', 'label' => 'Mesa B03', 'kind' => 'mesa', 'capacity' => 6, 'pos_x' => 630, 'pos_y' => 470],
                    ['sector_name' => 'Varanda VIP', 'label' => 'Mesa B04', 'kind' => 'mesa', 'capacity' => 6, 'pos_x' => 820, 'pos_y' => 470],
                ],
            );

            $theaterMap = $this->upsertVenueMap(
                $tenant,
                'Teatro Showcase',
                1700,
                1100,
                $this->buildRows('Plateia A', 'A', 10, 150, 250)
                    ->merge($this->buildRows('Plateia B', 'B', 10, 150, 410))
                    ->merge($this->buildRows('Plateia C', 'C', 8, 220, 570, true))
                    ->all(),
            );

            $arenaMap = $this->upsertVenueMap(
                $tenant,
                'Arena Multi Experience',
                1800,
                1100,
                [
                    ['sector_name' => 'Camarotes', 'label' => 'Camarote 01', 'kind' => 'camarote', 'capacity' => 8, 'pos_x' => 180, 'pos_y' => 160],
                    ['sector_name' => 'Camarotes', 'label' => 'Camarote 02', 'kind' => 'camarote', 'capacity' => 8, 'pos_x' => 420, 'pos_y' => 160],
                    ['sector_name' => 'Mesas', 'label' => 'Mesa Mix 01', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 220, 'pos_y' => 430],
                    ['sector_name' => 'Mesas', 'label' => 'Mesa Mix 02', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 380, 'pos_y' => 430],
                    ['sector_name' => 'Mesas', 'label' => 'Mesa Mix 03', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 540, 'pos_y' => 430],
                    ['sector_name' => 'Mesas', 'label' => 'Mesa Mix 04', 'kind' => 'mesa', 'capacity' => 4, 'pos_x' => 700, 'pos_y' => 430],
                    ['sector_name' => 'Premium', 'label' => 'P1', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1050, 'pos_y' => 290],
                    ['sector_name' => 'Premium', 'label' => 'P2', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1110, 'pos_y' => 290],
                    ['sector_name' => 'Premium', 'label' => 'P3', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1170, 'pos_y' => 290],
                    ['sector_name' => 'Premium', 'label' => 'P4', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1230, 'pos_y' => 290],
                    ['sector_name' => 'Premium', 'label' => 'P5', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1290, 'pos_y' => 290],
                    ['sector_name' => 'Premium', 'label' => 'P6', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1350, 'pos_y' => 290],
                    ['sector_name' => 'Premium', 'label' => 'P7', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1050, 'pos_y' => 360],
                    ['sector_name' => 'Premium', 'label' => 'P8', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1110, 'pos_y' => 360],
                    ['sector_name' => 'Premium', 'label' => 'P9', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1170, 'pos_y' => 360],
                    ['sector_name' => 'Premium', 'label' => 'P10', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1230, 'pos_y' => 360],
                    ['sector_name' => 'Premium', 'label' => 'P11', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1290, 'pos_y' => 360],
                    ['sector_name' => 'Premium', 'label' => 'P12', 'kind' => 'assento', 'capacity' => 1, 'pos_x' => 1350, 'pos_y' => 360],
                ],
            );

            $events = [
                $this->upsertFestivalEvent($tenant, $categories['Shows']),
                $this->upsertSummitEvent($tenant, $categories['Corporativo']),
                $this->upsertTableEvent($tenant, $categories['Experiencias premium'], $loungeMap),
                $this->upsertSeatedEvent($tenant, $categories['Teatro'], $theaterMap),
                $this->upsertMixedEvent($tenant, $categories['Esportes'], $arenaMap),
            ];

            $this->printSummary($tenant, $user, $events);
        });

        $this->call(PegaTicketDemoOperationsSeeder::class);
    }

    private function upsertDemoUser(): User
    {
        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Demo PegaTicket',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
            ],
        );

        return $user;
    }

    private function upsertDemoTenant(int $planId): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => self::TENANT_SLUG],
            [
                'name' => 'PegaTicket Showcase',
                'razao_social' => 'PegaTicket Showcase Eventos LTDA',
                'cnpj' => '12345678000199',
                'plan_id' => $planId,
                'is_active' => true,
                'trial_ends_at' => CarbonImmutable::now()->addMonths(6),
                'email' => self::DEMO_EMAIL,
                'phone' => '11940000000',
                'mobile_phone' => '11940000000',
                'whatsapp' => '11940000000',
                'instagram' => '@pegaticket.showcase',
                'facebook' => 'pegaticket.showcase',
            ],
        );

        return $tenant;
    }

    private function upsertTenantSettings(Tenant $tenant): void
    {
        TenantSettings::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'accepted_payment_methods' => ['pix', 'credit_card', 'debit_card', 'cash'],
                'payment_receiving_method' => 'manual',
                'payment_pix_key' => null,
                'service_fee_percent' => 10,
                'service_fee_mandatory' => false,
                'storefront_enabled' => true,
                'catalog_layout' => 'list',
                'hold_duration_minutes' => 20,
            ],
        );
    }

    /**
     * @return array<string, EventCategory>
     */
    private function upsertCategories(Tenant $tenant): array
    {
        $categories = [];
        $priority = 10;

        foreach (['Shows', 'Corporativo', 'Experiencias premium', 'Teatro', 'Esportes'] as $name) {
            /** @var EventCategory $category */
            $category = EventCategory::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['priority' => $priority, 'is_active' => true],
            );

            $categories[$name] = $category;
            $priority += 10;
        }

        return $categories;
    }

    /**
     * @param list<array<string, int|string|bool|null>> $seats
     */
    private function upsertVenueMap(Tenant $tenant, string $venueName, int $width, int $height, array $seats): VenueMapVersion
    {
        /** @var Venue $venue */
        $venue = Venue::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $venueName],
            [
                'width' => $width,
                'height' => $height,
                'is_active' => true,
            ],
        );

        /** @var VenueMapVersion $mapVersion */
        $mapVersion = VenueMapVersion::query()->updateOrCreate(
            ['venue_id' => $venue->id, 'version_number' => 1],
            [
                'tenant_id' => $tenant->id,
                'is_published' => true,
            ],
        );

        foreach ($seats as $seatData) {
            $seat = Seat::withTrashed()
                ->where('venue_map_version_id', $mapVersion->id)
                ->where('label', (string) $seatData['label'])
                ->first();

            if (!$seat) {
                $seat = new Seat();
            }

            if ($seat->trashed()) {
                $seat->restore();
            }

            $seat->fill([
                'tenant_id' => $tenant->id,
                'venue_map_version_id' => $mapVersion->id,
                'sector_name' => $seatData['sector_name'],
                'label' => $seatData['label'],
                'kind' => $seatData['kind'],
                'capacity' => $seatData['capacity'],
                'pos_x' => $seatData['pos_x'],
                'pos_y' => $seatData['pos_y'],
                'is_accessible' => (bool) ($seatData['is_accessible'] ?? false),
                'status' => $seatData['status'] ?? 'disponivel',
            ]);
            $seat->save();
        }

        return $mapVersion->fresh();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, int|string|bool>>
     */
    private function buildRows(string $sectorName, string $rowPrefix, int $count, int $startX, int $y, bool $markAccessible = false)
    {
        return collect(range(1, $count))->map(function (int $index) use ($sectorName, $rowPrefix, $startX, $y, $markAccessible) {
            return [
                'sector_name' => $sectorName,
                'label' => $rowPrefix . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'kind' => 'assento',
                'capacity' => 1,
                'pos_x' => $startX + (($index - 1) * 60),
                'pos_y' => $y,
                'is_accessible' => $markAccessible && $index <= 2,
            ];
        });
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    private function upsertFestivalEvent(Tenant $tenant, EventCategory $category): array
    {
        $startsAt = CarbonImmutable::now()->addDays(18)->setTime(16, 0);
        $endsAt = $startsAt->addHours(8);

        $event = $this->upsertEvent($tenant, [
            'event_category_id' => $category->id,
            'name' => 'Festival PegaTicket 2026',
            'slug' => 'festival-pegaticket-2026',
            'description_short' => 'Evento aberto com pista, meia-entrada e adicionais para demonstrar bilheteria online.',
            'description_full' => 'Evento de showcase para apresentar compra de ingressos unitarios, virada de lote, adicionais e sessoes diferentes no mesmo tenant.',
            'type' => 'ingresso',
            'location_name' => 'Parque de Eventos Showcase',
            'location_address' => 'Av. Central, 1000 - Sao Paulo/SP',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        $sessaoSexta = $this->upsertSession($tenant, $event, [
            'name' => 'Sexta de abertura',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(4),
            'gate_opens_at' => $startsAt->subHour(),
            'capacity' => 800,
            'status' => 'publicado',
            'sales_start_at' => CarbonImmutable::now()->subDays(2),
            'sales_end_at' => $startsAt->subHour(),
        ]);

        $sessaoSabado = $this->upsertSession($tenant, $event, [
            'name' => 'Sabado principal',
            'starts_at' => $startsAt->addDay()->setTime(18, 0),
            'ends_at' => $startsAt->addDay()->setTime(23, 30),
            'gate_opens_at' => $startsAt->addDay()->setTime(17, 0),
            'capacity' => 1200,
            'status' => 'publicado',
            'sales_start_at' => CarbonImmutable::now()->subDays(2),
            'sales_end_at' => $startsAt->addDay()->setTime(22, 0),
        ]);

        $vip = $this->upsertTicketType($tenant, $event, [
            'event_session_id' => $sessaoSabado->id,
            'name' => 'VIP Frontstage',
            'description' => 'Acesso premium em frente ao palco principal.',
            'price' => 240,
            'quantity_available' => 250,
            'min_per_order' => 1,
            'max_per_order' => 6,
            'sales_start_at' => CarbonImmutable::now()->subDays(2),
            'sales_end_at' => $startsAt->addDay()->setTime(21, 30),
            'status' => 'ativo',
            'sku' => 'SHOW-VIP-01',
            'unit' => 'un',
        ]);
        $this->upsertBatch($tenant, $vip, [
            'name' => 'Lote promocional VIP',
            'price' => 210,
            'quantity' => 80,
            'starts_at' => CarbonImmutable::now()->subDays(2),
            'ends_at' => CarbonImmutable::now()->addDays(4),
            'priority' => 1,
            'auto_advance' => true,
            'status' => 'ativo',
        ]);
        $this->upsertBatch($tenant, $vip, [
            'name' => 'Lote regular VIP',
            'price' => 240,
            'quantity' => 170,
            'starts_at' => CarbonImmutable::now()->addDays(4),
            'ends_at' => $startsAt->addDay()->setTime(21, 30),
            'priority' => 2,
            'auto_advance' => true,
            'status' => 'ativo',
        ]);

        $pista = $this->upsertTicketType($tenant, $event, [
            'event_session_id' => $sessaoSexta->id,
            'name' => 'Pista inteira',
            'description' => 'Ingresso inteiro para o dia de abertura.',
            'price' => 120,
            'quantity_available' => 700,
            'min_per_order' => 1,
            'max_per_order' => 8,
            'sales_start_at' => CarbonImmutable::now()->subDays(2),
            'sales_end_at' => $startsAt->subHour(),
            'status' => 'ativo',
            'sku' => 'SHOW-PISTA-01',
            'unit' => 'un',
        ]);
        $this->upsertBatch($tenant, $pista, [
            'name' => 'Primeiro lote',
            'price' => 95,
            'quantity' => 250,
            'starts_at' => CarbonImmutable::now()->subDays(2),
            'ends_at' => CarbonImmutable::now()->addDays(3),
            'priority' => 1,
            'auto_advance' => true,
            'status' => 'ativo',
        ]);
        $this->upsertBatch($tenant, $pista, [
            'name' => 'Segundo lote',
            'price' => 120,
            'quantity' => 450,
            'starts_at' => CarbonImmutable::now()->addDays(3),
            'ends_at' => $startsAt->subHour(),
            'priority' => 2,
            'auto_advance' => true,
            'status' => 'ativo',
        ]);

        $this->upsertEventProduct($tenant, $event, [
            'name' => 'Estacionamento oficial',
            'description' => 'Vaga para um veiculo durante o festival.',
            'price' => 35,
            'quantity_available' => 200,
            'max_per_order' => 2,
            'sales_start_at' => CarbonImmutable::now()->subDays(2),
            'sales_end_at' => $endsAt,
            'kind' => 'parking',
            'requires_plate' => true,
            'requires_model' => true,
            'requires_color' => true,
            'status' => 'ativo',
        ]);
        $this->upsertEventProduct($tenant, $event, [
            'name' => 'Copo colecionavel',
            'description' => 'Adicional simples para demonstracao de upsell.',
            'price' => 18,
            'quantity_available' => 500,
            'max_per_order' => 4,
            'sales_start_at' => CarbonImmutable::now()->subDays(2),
            'sales_end_at' => $endsAt,
            'kind' => 'addon',
            'requires_plate' => false,
            'requires_model' => false,
            'requires_color' => false,
            'status' => 'ativo',
        ]);

        return $this->eventSummary($tenant, $event);
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    private function upsertSummitEvent(Tenant $tenant, EventCategory $category): array
    {
        $startsAt = CarbonImmutable::now()->addDays(26)->setTime(8, 30);
        $endsAt = $startsAt->addHours(10);

        $event = $this->upsertEvent($tenant, [
            'event_category_id' => $category->id,
            'name' => 'Summit de Produtores 2026',
            'slug' => 'summit-produtores-2026',
            'description_short' => 'Exemplo de inscricao para congresso, sem mapa de lugares.',
            'description_full' => 'Evento corporativo com inscricao, lote early bird, lote regular e adicional de certificado premium.',
            'type' => 'inscricao',
            'location_name' => 'Centro de Convencoes Paulista',
            'location_address' => 'Rua das Conferencias, 500 - Sao Paulo/SP',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        $standard = $this->upsertTicketType($tenant, $event, [
            'event_session_id' => null,
            'name' => 'Inscricao standard',
            'description' => 'Acesso completo ao congresso.',
            'price' => 390,
            'quantity_available' => 400,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $startsAt->subHour(),
            'status' => 'ativo',
            'sku' => 'SUMMIT-STD-01',
            'unit' => 'un',
        ]);
        $this->upsertBatch($tenant, $standard, [
            'name' => 'Early bird',
            'price' => 290,
            'quantity' => 120,
            'starts_at' => CarbonImmutable::now()->subDays(1),
            'ends_at' => CarbonImmutable::now()->addDays(8),
            'priority' => 1,
            'auto_advance' => true,
            'status' => 'ativo',
        ]);
        $this->upsertBatch($tenant, $standard, [
            'name' => 'Lote final',
            'price' => 390,
            'quantity' => 280,
            'starts_at' => CarbonImmutable::now()->addDays(8),
            'ends_at' => $startsAt->subHour(),
            'priority' => 2,
            'auto_advance' => true,
            'status' => 'ativo',
        ]);

        $this->upsertEventProduct($tenant, $event, [
            'name' => 'Certificado premium',
            'description' => 'Versao impressa e digital do certificado.',
            'price' => 49,
            'quantity_available' => 300,
            'max_per_order' => 5,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $startsAt,
            'kind' => 'addon',
            'requires_plate' => false,
            'requires_model' => false,
            'requires_color' => false,
            'status' => 'ativo',
        ]);

        return $this->eventSummary($tenant, $event);
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    private function upsertTableEvent(Tenant $tenant, EventCategory $category, VenueMapVersion $mapVersion): array
    {
        $startsAt = CarbonImmutable::now()->addDays(32)->setTime(20, 0);
        $endsAt = $startsAt->addHours(6);

        $event = $this->upsertEvent($tenant, [
            'event_category_id' => $category->id,
            'venue_map_version_id' => $mapVersion->id,
            'name' => 'Noite Premium Lounge',
            'slug' => 'noite-premium-lounge',
            'description_short' => 'Exemplo de venda por mesa com capacidades diferentes.',
            'description_full' => 'Evento focado em reservas de mesas para apresentar o fluxo de escolha de lugar com capacidades variadas.',
            'type' => 'mesa',
            'location_name' => 'Roof Lounge Experience',
            'location_address' => 'Alameda Premium, 88 - Sao Paulo/SP',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        $mesa = $this->upsertTicketType($tenant, $event, [
            'event_session_id' => null,
            'name' => 'Reserva de mesa',
            'description' => 'Escolha sua mesa no mapa e conclua a compra online.',
            'price' => 700,
            'quantity_available' => 8,
            'min_per_order' => 1,
            'max_per_order' => 1,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $startsAt->subHour(),
            'status' => 'ativo',
            'sku' => 'LOUNGE-MESA-01',
            'unit' => 'un',
        ]);
        $this->upsertBatch($tenant, $mesa, [
            'name' => 'Lote unico',
            'price' => 700,
            'quantity' => 8,
            'starts_at' => CarbonImmutable::now()->subDays(1),
            'ends_at' => $startsAt->subHour(),
            'priority' => 1,
            'auto_advance' => false,
            'status' => 'ativo',
        ]);

        $this->upsertEventProduct($tenant, $event, [
            'name' => 'Combo espumante',
            'description' => 'Adicional para demonstrar mesa com consumo opcional.',
            'price' => 220,
            'quantity_available' => 50,
            'max_per_order' => 3,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $endsAt,
            'kind' => 'addon',
            'requires_plate' => false,
            'requires_model' => false,
            'requires_color' => false,
            'status' => 'ativo',
        ]);

        return $this->eventSummary($tenant, $event);
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    private function upsertSeatedEvent(Tenant $tenant, EventCategory $category, VenueMapVersion $mapVersion): array
    {
        $startsAt = CarbonImmutable::now()->addDays(40)->setTime(19, 30);
        $endsAt = $startsAt->addHours(3);

        $event = $this->upsertEvent($tenant, [
            'event_category_id' => $category->id,
            'venue_map_version_id' => $mapVersion->id,
            'name' => 'Teatro das Estrelas',
            'slug' => 'teatro-das-estrelas',
            'description_short' => 'Exemplo com assentos marcados e sessoes por horario.',
            'description_full' => 'Evento teatral para demonstrar escolha de assento, setores e sessao dedicada.',
            'type' => 'assento',
            'location_name' => 'Teatro Showcase',
            'location_address' => 'Rua da Plateia, 45 - Sao Paulo/SP',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        $sessao = $this->upsertSession($tenant, $event, [
            'name' => 'Sessao unica 19h30',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'gate_opens_at' => $startsAt->subMinutes(40),
            'capacity' => 28,
            'status' => 'publicado',
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $startsAt->subMinutes(20),
        ]);

        $plateia = $this->upsertTicketType($tenant, $event, [
            'event_session_id' => $sessao->id,
            'name' => 'Plateia inteira',
            'description' => 'Assento marcado para a peca.',
            'price' => 160,
            'quantity_available' => 28,
            'min_per_order' => 1,
            'max_per_order' => 6,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $startsAt->subMinutes(20),
            'status' => 'ativo',
            'sku' => 'TEATRO-PLATEIA-01',
            'unit' => 'un',
        ]);
        $this->upsertBatch($tenant, $plateia, [
            'name' => 'Lote principal',
            'price' => 160,
            'quantity' => 28,
            'starts_at' => CarbonImmutable::now()->subDays(1),
            'ends_at' => $startsAt->subMinutes(20),
            'priority' => 1,
            'auto_advance' => false,
            'status' => 'ativo',
        ]);

        return $this->eventSummary($tenant, $event);
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    private function upsertMixedEvent(Tenant $tenant, EventCategory $category, VenueMapVersion $mapVersion): array
    {
        $startsAt = CarbonImmutable::now()->addDays(48)->setTime(17, 0);
        $endsAt = $startsAt->addHours(7);

        $event = $this->upsertEvent($tenant, [
            'event_category_id' => $category->id,
            'venue_map_version_id' => $mapVersion->id,
            'name' => 'Arena Experience Cup',
            'slug' => 'arena-experience-cup',
            'description_short' => 'Exemplo misto com camarote, mesas e assentos premium.',
            'description_full' => 'Evento para demonstrar o modo misto do sistema, combinando mapa com camarotes, mesas e assentos premium.',
            'type' => 'misto',
            'location_name' => 'Arena Multi Experience',
            'location_address' => 'Av. Arena, 900 - Sao Paulo/SP',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        $premium = $this->upsertTicketType($tenant, $event, [
            'event_session_id' => null,
            'name' => 'Premium numerado',
            'description' => 'Assentos premium para o evento esportivo.',
            'price' => 280,
            'quantity_available' => 12,
            'min_per_order' => 1,
            'max_per_order' => 4,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $startsAt->subHour(),
            'status' => 'ativo',
            'sku' => 'ARENA-PREMIUM-01',
            'unit' => 'un',
        ]);
        $this->upsertBatch($tenant, $premium, [
            'name' => 'Lote premium',
            'price' => 280,
            'quantity' => 12,
            'starts_at' => CarbonImmutable::now()->subDays(1),
            'ends_at' => $startsAt->subHour(),
            'priority' => 1,
            'auto_advance' => false,
            'status' => 'ativo',
        ]);

        $vip = $this->upsertTicketType($tenant, $event, [
            'event_session_id' => null,
            'name' => 'Camarote experience',
            'description' => 'Compra para setores especiais do mapa.',
            'price' => 980,
            'quantity_available' => 6,
            'min_per_order' => 1,
            'max_per_order' => 2,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $startsAt->subHour(),
            'status' => 'ativo',
            'sku' => 'ARENA-CAMAROTE-01',
            'unit' => 'un',
        ]);
        $this->upsertBatch($tenant, $vip, [
            'name' => 'Lote experience',
            'price' => 980,
            'quantity' => 6,
            'starts_at' => CarbonImmutable::now()->subDays(1),
            'ends_at' => $startsAt->subHour(),
            'priority' => 2,
            'auto_advance' => false,
            'status' => 'ativo',
        ]);

        $this->upsertEventProduct($tenant, $event, [
            'name' => 'Parking VIP',
            'description' => 'Vaga premium para o evento misto.',
            'price' => 50,
            'quantity_available' => 80,
            'max_per_order' => 2,
            'sales_start_at' => CarbonImmutable::now()->subDays(1),
            'sales_end_at' => $endsAt,
            'kind' => 'parking',
            'requires_plate' => true,
            'requires_model' => true,
            'requires_color' => true,
            'status' => 'ativo',
        ]);

        return $this->eventSummary($tenant, $event);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertEvent(Tenant $tenant, array $data): Event
    {
        /** @var Event $event */
        $event = Event::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $data['slug']],
            $data,
        );

        return $event;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertSession(Tenant $tenant, Event $event, array $data): EventSession
    {
        /** @var EventSession $session */
        $session = EventSession::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'name' => $data['name']],
            $data + ['tenant_id' => $tenant->id, 'event_id' => $event->id],
        );

        return $session;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertTicketType(Tenant $tenant, Event $event, array $data): TicketType
    {
        /** @var TicketType $ticketType */
        $ticketType = TicketType::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'name' => $data['name']],
            $data + ['tenant_id' => $tenant->id, 'event_id' => $event->id],
        );

        return $ticketType;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertBatch(Tenant $tenant, TicketType $ticketType, array $data): TicketBatch
    {
        /** @var TicketBatch $batch */
        $batch = TicketBatch::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'ticket_type_id' => $ticketType->id, 'name' => $data['name']],
            $data + ['tenant_id' => $tenant->id, 'ticket_type_id' => $ticketType->id],
        );

        return $batch;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertEventProduct(Tenant $tenant, Event $event, array $data): EventProduct
    {
        /** @var EventProduct $product */
        $product = EventProduct::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'name' => $data['name']],
            $data + ['tenant_id' => $tenant->id, 'event_id' => $event->id],
        );

        return $product;
    }

    /**
     * @return array{name: string, slug: string, url: string}
     */
    private function eventSummary(Tenant $tenant, Event $event): array
    {
        return [
            'name' => $event->name,
            'slug' => $event->slug,
            'url' => $this->storefrontBaseUrl($tenant).'/'.$event->slug,
        ];
    }

    private function storefrontBaseUrl(Tenant $tenant): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return $frontendUrl.'/eventos/'.$tenant->slug;
    }

    /**
     * @param array<int, array{name: string, slug: string, url: string}> $events
     */
    private function printSummary(Tenant $tenant, User $user, array $events): void
    {
        if (!$this->command) {
            return;
        }

        $this->command->info('Seeder de demonstracao do PegaTicket executado com sucesso.');
        $this->command->line('Usuario: '.self::DEMO_EMAIL);
        $this->command->line('Senha: '.self::DEMO_PASSWORD);
        $this->command->line('Empresa: '.$tenant->name);
        $this->command->line('Slug da empresa: '.$tenant->slug);
        $this->command->line('Bilheteria externa: '.$this->storefrontBaseUrl($tenant));
        $this->command->line('Painel do operador: '.rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/').'/login');
        $this->command->line('Responsavel: '.$user->name);

        foreach ($events as $event) {
            $this->command->line('- '.$event['name'].': '.$event['url']);
        }
    }
}
