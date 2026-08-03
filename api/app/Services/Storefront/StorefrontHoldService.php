<?php

namespace App\Services\Storefront;

use App\Models\Event\Event;
use App\Models\Event\EventProduct;
use App\Models\Event\EventSession;
use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Inventory\InventoryHold;
use App\Models\Inventory\InventoryHoldItem;
use App\Models\Sale\SaleItem;
use App\Models\Venue\Seat;
use App\Services\Tenant\TenantSettingsService;
use App\Support\MediaUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StorefrontHoldService
{
    private const DEFAULT_HOLD_TTL_MINUTES = 15;

    public function __construct(
        private StorefrontCatalogService $catalogService,
        private TenantSettingsService $tenantSettingsService,
        private SeatAllocationService $seatAllocationService,
    ) {}

    /**
     * Prazo de reserva (spec 5.9): configurável por tenant via
     * tenant_settings.hold_duration_minutes, default 15 quando ausente.
     */
    private function holdTtlMinutes(int $tenantId): int
    {
        $configured = $this->tenantSettingsService->getForTenant($tenantId)->hold_duration_minutes;

        return $configured !== null && $configured > 0 ? $configured : self::DEFAULT_HOLD_TTL_MINUTES;
    }

    public function availability(string $slug, string $eventSlug, ?string $sessionUuid = null): array
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);
        $event = $this->findReservableEvent($tenant->id, $eventSlug);

        $this->expireHolds($tenant->id, $event->id);

        $session = $sessionUuid ? $this->resolveSession($event, $sessionUuid) : null;
        $eventHasSessions = $event->sessions->isNotEmpty();

        $ticketTypes = $eventHasSessions && ! $session
            ? collect()
            : $this->buildTicketTypesAvailability($event, $session);

        return [
            'server_time' => now(),
            'default_hold_seconds' => $this->holdTtlMinutes($tenant->id) * 60,
            'event' => [
                'uuid' => $event->uuid,
                'name' => $event->name,
                'slug' => $event->slug,
                'venue' => $event->venueMapVersion?->venue ? [
                    'uuid' => $event->venueMapVersion->venue->uuid,
                    'name' => $event->venueMapVersion->venue->name,
                    'map_version_uuid' => $event->venueMapVersion->uuid,
                    'map_version_number' => $event->venueMapVersion->version_number,
                    'background_image_url' => MediaUrl::resolvePublic(
                        $event->venueMapVersion->venue->background_image_path,
                        $event->venueMapVersion->venue->background_image_data ?? $event->venueMapVersion->venue->background_image_mime,
                        '/api/v1/venues/'.$event->venueMapVersion->venue->uuid.'/image',
                        null,
                        'venue'
                    ),
                    'width' => $event->venueMapVersion->venue->width,
                    'height' => $event->venueMapVersion->venue->height,
                ] : null,
            ],
            'requires_session_selection' => $eventHasSessions,
            'selected_session_uuid' => $session?->uuid,
            'sessions' => $event->sessions
                ->map(fn (EventSession $item) => [
                    'uuid' => $item->uuid,
                    'name' => $item->name,
                    'starts_at' => $item->starts_at,
                    'ends_at' => $item->ends_at,
                    'gate_opens_at' => $item->gate_opens_at,
                    'capacity' => $item->capacity,
                    'status' => $item->status,
                    'sales_start_at' => $item->sales_start_at,
                    'sales_end_at' => $item->sales_end_at,
                ])
                ->values(),
            'ticket_types' => $ticketTypes->values(),
            'event_products' => $this->buildEventProductsAvailability($event)->values(),
            'seats' => $this->buildSeatAvailability($event)->values(),
        ];
    }

    public function createHold(string $slug, string $eventSlug, array $payload, ?FinalCustomer $customer = null): InventoryHold
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);
        $event = $this->findReservableEvent($tenant->id, $eventSlug);

        return DB::transaction(function () use ($tenant, $event, $payload, $customer) {
            $this->expireHolds($tenant->id, $event->id);

            $session = $this->resolveRequestedSession($event, $payload['session_uuid'] ?? null);
            $expiresAt = now()->addMinutes($this->holdTtlMinutes($tenant->id));
            $items = $this->expandAutoSelectSeatItems($tenant->id, $event, collect($payload['items']));

            $this->assertNoDuplicateSeats($items);

            $hold = InventoryHold::create([
                'tenant_id' => $tenant->id,
                'final_customer_id' => $customer?->id,
                'event_id' => $event->id,
                'event_session_id' => $session?->id,
                'session_token' => $payload['session_token'],
                'status' => InventoryHold::STATUS_RESERVED,
                'origin' => 'storefront',
                'expires_at' => $expiresAt,
            ]);

            foreach ($items as $item) {
                $ticketType = ! empty($item['ticket_type_uuid']) ? $this->lockTicketType($tenant->id, $event->id, $item['ticket_type_uuid']) : null;
                $eventProduct = ! empty($item['event_product_uuid']) ? $this->lockEventProduct($tenant->id, $event->id, $item['event_product_uuid']) : null;
                $seat = ! empty($item['seat_uuid']) ? $this->lockSeatForEvent($event, $item['seat_uuid']) : null;
                $quantity = (int) $item['quantity'];

                $this->assertValidHoldItem($event, $session, $ticketType, $eventProduct, $seat, $quantity);

                $selectedBatch = $ticketType ? $this->selectBatchForTicketType($ticketType, $quantity) : null;
                $available = $ticketType
                    ? $this->resolveTicketTypeAvailability($ticketType, $selectedBatch)
                    : $this->calculateAvailableUnits($ticketType, $eventProduct, $seat, $selectedBatch);

                if ($available < $quantity) {
                    abort(422, __('messages.inventory_hold.insufficient_availability'));
                }

                $unitPrice = $ticketType
                    ? $this->resolveTicketTypeEffectivePrice($ticketType, $selectedBatch)
                    : ($eventProduct ? $this->resolveEffectiveUnitPrice($eventProduct) : null);

                InventoryHoldItem::create([
                    'tenant_id' => $tenant->id,
                    'inventory_hold_id' => $hold->id,
                    'ticket_type_id' => $ticketType?->id,
                    'event_product_id' => $eventProduct?->id,
                    'seat_id' => $seat?->id,
                    'ticket_batch_id' => $selectedBatch?->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'meta' => $seat ? ['seat_label' => $seat->label] : null,
                ]);
            }

            return $this->loadHold($hold);
        });
    }

    public function showHold(string $slug, string $holdUuid, ?string $sessionToken, ?FinalCustomer $customer = null): InventoryHold
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);

        $hold = $this->resolveAccessibleHold($tenant->id, $holdUuid, $sessionToken, $customer);
        $this->expireSingleHold($hold);

        return $this->loadHold($hold->fresh());
    }

    public function renewHold(string $slug, string $holdUuid, ?string $sessionToken, ?FinalCustomer $customer = null): InventoryHold
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);

        return DB::transaction(function () use ($tenant, $holdUuid, $sessionToken, $customer) {
            $hold = $this->resolveAccessibleHold($tenant->id, $holdUuid, $sessionToken, $customer, true);
            $this->expireSingleHold($hold);

            if ($hold->status !== InventoryHold::STATUS_RESERVED) {
                abort(422, __('messages.inventory_hold.not_active'));
            }

            $hold->forceFill([
                'expires_at' => now()->addMinutes($this->holdTtlMinutes($tenant->id)),
            ])->save();

            return $this->loadHold($hold->fresh());
        });
    }

    public function releaseHold(string $slug, string $holdUuid, ?string $sessionToken, ?FinalCustomer $customer = null): InventoryHold
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);

        return DB::transaction(function () use ($tenant, $holdUuid, $sessionToken, $customer) {
            $hold = $this->resolveAccessibleHold($tenant->id, $holdUuid, $sessionToken, $customer, true);
            $this->expireSingleHold($hold);

            if ($hold->status === InventoryHold::STATUS_RESERVED) {
                $hold->forceFill([
                    'status' => InventoryHold::STATUS_ABANDONED,
                ])->save();
            }

            return $this->loadHold($hold->fresh());
        });
    }

    private function findReservableEvent(int $tenantId, string $eventSlug): Event
    {
        return Event::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $eventSlug)
            ->where('status', 'publicado')
            ->where('visibility', 'public')
            ->whereNull('deleted_at')
            ->with([
                'venueMapVersion.venue',
                'venueMapVersion.seats' => fn ($q) => $q->whereNull('deleted_at')->orderBy('label'),
                'sessions' => fn ($q) => $q
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['cancelado', 'arquivado'])
                    ->orderBy('starts_at'),
                'ticketTypes' => fn ($q) => $q
                    ->where('status', 'ativo')
                    ->whereNull('deleted_at')
                    ->with(['event', 'session', 'batches' => fn ($batch) => $batch->whereNull('deleted_at')->orderBy('priority')]),
                'eventProducts' => fn ($q) => $q
                    ->where('status', 'ativo')
                    ->whereNull('deleted_at')
                    ->orderBy('name'),
            ])
            ->firstOrFail();
    }

    private function buildTicketTypesAvailability(Event $event, ?EventSession $session): Collection
    {
        return $event->ticketTypes
            ->filter(function (TicketType $ticketType) use ($session) {
                if ($session === null) {
                    return $ticketType->event_session_id === null;
                }

                return $ticketType->event_session_id === null || (int) $ticketType->event_session_id === (int) $session->id;
            })
            ->map(function (TicketType $ticketType) {
                $batch = $this->findActiveBatch($ticketType);
                $available = $this->resolveTicketTypeAvailability($ticketType, $batch);

                return [
                    'uuid' => $ticketType->uuid,
                    'name' => $ticketType->name,
                    'description' => $ticketType->description,
                    'session_uuid' => $ticketType->session?->uuid,
                    'base_price' => (float) $ticketType->price,
                    'effective_price' => $this->resolveTicketTypeEffectivePrice($ticketType, $batch),
                    'available_quantity' => max(0, $available),
                    'min_per_order' => $ticketType->min_per_order,
                    'max_per_order' => $ticketType->max_per_order,
                    'sales_start_at' => $ticketType->sales_start_at,
                    'sales_end_at' => $ticketType->sales_end_at,
                    'active_batch' => $batch ? [
                        'uuid' => $batch->uuid,
                        'name' => $batch->name,
                        'price' => (float) $batch->price,
                        'quantity_available' => max(0, $this->resolveTicketTypeAvailability($ticketType, $batch)),
                    ] : null,
                    'requires_seat_selection' => in_array($ticketType->event?->type, ['assento', 'mesa', 'misto'], true),
                ];
            });
    }

    private function buildEventProductsAvailability(Event $event): Collection
    {
        return $event->eventProducts->map(function (EventProduct $eventProduct) {
            $available = $this->calculateAvailableUnits(null, $eventProduct, null, null);

            return [
                'uuid' => $eventProduct->uuid,
                'name' => $eventProduct->name,
                'description' => $eventProduct->description,
                'kind' => $eventProduct->kind,
                'price' => (float) $eventProduct->price,
                'available_quantity' => max(0, $available),
                'max_per_order' => $eventProduct->max_per_order,
                'sales_start_at' => $eventProduct->sales_start_at,
                'sales_end_at' => $eventProduct->sales_end_at,
            ];
        });
    }

    private function buildSeatAvailability(Event $event): Collection
    {
        if (! $event->venueMapVersion) {
            return collect();
        }

        $reservedSeatQuantities = InventoryHoldItem::query()
            ->join('inventory_holds', 'inventory_holds.id', '=', 'inventory_hold_items.inventory_hold_id')
            ->where('inventory_holds.tenant_id', $event->tenant_id)
            ->where('inventory_holds.event_id', $event->id)
            ->where('inventory_holds.status', InventoryHold::STATUS_RESERVED)
            ->where('inventory_holds.expires_at', '>', now())
            ->whereNotNull('inventory_hold_items.seat_id')
            ->selectRaw('inventory_hold_items.seat_id as seat_id, SUM(inventory_hold_items.quantity) as reserved_quantity')
            ->groupBy('inventory_hold_items.seat_id')
            ->pluck('reserved_quantity', 'seat_id');

        $soldSeatQuantities = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $event->tenant_id)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->where('sales.status', '!=', 'rejected')
            ->whereNotNull('sale_items.seat_id')
            ->whereNull('sale_items.deleted_at')
            ->selectRaw('sale_items.seat_id as seat_id, SUM(sale_items.quantity) as sold_quantity')
            ->groupBy('sale_items.seat_id')
            ->pluck('sold_quantity', 'seat_id');

        return $event->venueMapVersion->seats->map(function (Seat $seat) use ($reservedSeatQuantities, $soldSeatQuantities) {
            $availabilityStatus = 'disponivel';
            $availableQuantity = $this->calculateAvailableUnits(null, null, $seat, null);
            $seatCapacity = max(1, (int) ($seat->capacity ?? 1));
            $reservedQuantity = (int) ($reservedSeatQuantities[$seat->id] ?? 0);
            $soldQuantity = (int) ($soldSeatQuantities[$seat->id] ?? 0);
            $isExclusiveSeat = $this->seatRequiresExclusiveReservation($seat);

            if ($seat->status !== 'disponivel') {
                $availabilityStatus = 'indisponivel';
            } elseif ($isExclusiveSeat && $soldQuantity > 0) {
                $availabilityStatus = 'indisponivel';
            } elseif ($isExclusiveSeat && $reservedQuantity > 0) {
                $availabilityStatus = 'reservado';
            } elseif ($soldQuantity >= $seatCapacity) {
                $availabilityStatus = 'indisponivel';
            } elseif ($reservedQuantity > 0 || $availableQuantity <= 0) {
                $availabilityStatus = 'reservado';
            }

            return [
                'uuid' => $seat->uuid,
                'sector_name' => $seat->sector_name,
                'label' => $seat->label,
                'kind' => $seat->kind,
                'capacity' => $seat->capacity,
                'available_quantity' => $availableQuantity,
                'pos_x' => $seat->pos_x,
                'pos_y' => $seat->pos_y,
                'is_accessible' => $seat->is_accessible,
                'status' => $seat->status,
                'availability_status' => $availabilityStatus,
            ];
        });
    }

    private function findActiveBatch(TicketType $ticketType): ?TicketBatch
    {
        return $this->resolveBatchForStorefront($ticketType);
    }

    private function selectBatchForTicketType(TicketType $ticketType, int $quantity): ?TicketBatch
    {
        return $this->resolveBatchForStorefront($ticketType, $quantity);
    }

    private function batchIsActive(TicketBatch $batch): bool
    {
        if ($batch->status !== 'ativo') {
            return false;
        }

        if ($batch->starts_at && $batch->starts_at->isFuture()) {
            return false;
        }

        if ($batch->ends_at && $batch->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    private function resolveBatchForStorefront(TicketType $ticketType, ?int $requiredQuantity = null): ?TicketBatch
    {
        $batches = $ticketType->batches
            ->filter(fn (TicketBatch $batch) => $this->batchIsActive($batch))
            ->sortBy('priority')
            ->values();

        if ($batches->isEmpty()) {
            return null;
        }

        foreach ($batches as $batch) {
            $available = $this->calculateAvailableUnits($ticketType, null, null, $batch);

            if ($available > 0 && ($requiredQuantity === null || $available >= $requiredQuantity)) {
                return $batch;
            }

            if (! $this->shouldAutoAdvanceBatch($batch, $available)) {
                return $requiredQuantity === null ? $batch : null;
            }
        }

        return null;
    }

    private function shouldAutoAdvanceBatch(TicketBatch $batch, int $available): bool
    {
        return $batch->auto_advance && $available <= 0;
    }

    private function resolveTicketTypeAvailability(TicketType $ticketType, ?TicketBatch $batch): int
    {
        if ($batch) {
            return $this->calculateAvailableUnits($ticketType, null, null, $batch);
        }

        if ($ticketType->batches->isNotEmpty()) {
            return 0;
        }

        return $this->calculateAvailableUnits($ticketType, null, null, null);
    }

    private function resolveTicketTypeEffectivePrice(TicketType $ticketType, ?TicketBatch $batch): float
    {
        if ($batch) {
            return (float) $batch->price;
        }

        return $this->resolveEffectiveUnitPrice($ticketType);
    }

    private function calculateAvailableUnits(?TicketType $ticketType, ?EventProduct $eventProduct, ?Seat $seat, ?TicketBatch $ticketBatch): int
    {
        if ($seat) {
            if ($seat->status !== 'disponivel') {
                return 0;
            }

            $seatCapacity = max(1, (int) ($seat->capacity ?? 1));
            $reservedQuantity = $this->reservedSeatQuantity($seat->id);
            $soldQuantity = $this->soldSeatQuantity($seat->id);

            if ($this->seatRequiresExclusiveReservation($seat)) {
                return ($reservedQuantity > 0 || $soldQuantity > 0) ? 0 : $seatCapacity;
            }

            return max(0, $seatCapacity - $reservedQuantity - $soldQuantity);
        }

        if ($ticketBatch) {
            return max(0, $ticketBatch->quantity - $ticketBatch->quantity_sold - $this->reservedBatchQuantity($ticketBatch->id));
        }

        if ($ticketType) {
            return max(0, (int) ($ticketType->quantity_available ?? 0) - $this->reservedTicketTypeQuantity($ticketType->id));
        }

        if ($eventProduct) {
            return max(0, (int) ($eventProduct->quantity_available ?? 0) - $this->reservedEventProductQuantity($eventProduct->id));
        }

        return 0;
    }

    private function reservedTicketTypeQuantity(int $ticketTypeId): int
    {
        return (int) InventoryHoldItem::query()
            ->join('inventory_holds', 'inventory_holds.id', '=', 'inventory_hold_items.inventory_hold_id')
            ->where('inventory_hold_items.ticket_type_id', $ticketTypeId)
            ->where('inventory_holds.status', InventoryHold::STATUS_RESERVED)
            ->where('inventory_holds.expires_at', '>', now())
            ->sum('inventory_hold_items.quantity');
    }

    private function reservedEventProductQuantity(int $eventProductId): int
    {
        return (int) InventoryHoldItem::query()
            ->join('inventory_holds', 'inventory_holds.id', '=', 'inventory_hold_items.inventory_hold_id')
            ->where('inventory_hold_items.event_product_id', $eventProductId)
            ->where('inventory_holds.status', InventoryHold::STATUS_RESERVED)
            ->where('inventory_holds.expires_at', '>', now())
            ->sum('inventory_hold_items.quantity');
    }

    private function reservedBatchQuantity(int $ticketBatchId): int
    {
        return (int) InventoryHoldItem::query()
            ->join('inventory_holds', 'inventory_holds.id', '=', 'inventory_hold_items.inventory_hold_id')
            ->where('inventory_hold_items.ticket_batch_id', $ticketBatchId)
            ->where('inventory_holds.status', InventoryHold::STATUS_RESERVED)
            ->where('inventory_holds.expires_at', '>', now())
            ->sum('inventory_hold_items.quantity');
    }

    private function reservedSeatQuantity(int $seatId): int
    {
        return (int) InventoryHoldItem::query()
            ->join('inventory_holds', 'inventory_holds.id', '=', 'inventory_hold_items.inventory_hold_id')
            ->where('inventory_hold_items.seat_id', $seatId)
            ->where('inventory_holds.status', InventoryHold::STATUS_RESERVED)
            ->where('inventory_holds.expires_at', '>', now())
            ->sum('inventory_hold_items.quantity');
    }

    private function soldSeatQuantity(int $seatId): int
    {
        return (int) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.seat_id', $seatId)
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->where('sales.status', '!=', 'rejected')
            ->sum('sale_items.quantity');
    }

    private function resolveSession(Event $event, string $sessionUuid): EventSession
    {
        return $event->sessions->firstWhere('uuid', $sessionUuid) ?? abort(404);
    }

    private function resolveRequestedSession(Event $event, ?string $sessionUuid): ?EventSession
    {
        if ($event->sessions->isEmpty()) {
            return null;
        }

        if (! $sessionUuid) {
            abort(422, __('messages.inventory_hold.session_required'));
        }

        return $this->resolveSession($event, $sessionUuid);
    }

    private function assertValidHoldItem(
        Event $event,
        ?EventSession $session,
        ?TicketType $ticketType,
        ?EventProduct $eventProduct,
        ?Seat $seat,
        int $quantity
    ): void {
        if (($ticketType === null) === ($eventProduct === null)) {
            abort(422, __('messages.inventory_hold.invalid_item'));
        }

        if ($seat && ! $ticketType) {
            abort(422, __('messages.inventory_hold.seat_requires_ticket_type'));
        }

        if ($seat && $seat->kind === 'assento' && $quantity !== 1) {
            abort(422, __('messages.inventory_hold.seat_quantity_invalid'));
        }

        if ($seat && $this->seatRequiresExclusiveReservation($seat) && $quantity !== max(1, (int) ($seat->capacity ?? 1))) {
            abort(422, __('messages.inventory_hold.seat_capacity_exceeded'));
        }

        if ($seat && $quantity > max(1, (int) ($seat->capacity ?? 1))) {
            abort(422, __('messages.inventory_hold.seat_capacity_exceeded'));
        }

        if ($ticketType && $ticketType->max_per_order !== null && $quantity > $ticketType->max_per_order) {
            abort(422, __('messages.inventory_hold.max_per_order_exceeded'));
        }

        if ($eventProduct && $eventProduct->max_per_order !== null && $quantity > $eventProduct->max_per_order) {
            abort(422, __('messages.inventory_hold.max_per_order_exceeded'));
        }

        if ($ticketType && $ticketType->event_session_id !== null && $session && (int) $ticketType->event_session_id !== (int) $session->id) {
            abort(422, __('messages.inventory_hold.ticket_type_session_mismatch'));
        }

        if ($ticketType && $ticketType->event_session_id !== null && ! $session) {
            abort(422, __('messages.inventory_hold.session_required'));
        }

        if ($seat && (! $event->venueMapVersion || (int) $seat->venue_map_version_id !== (int) $event->venueMapVersion->id)) {
            abort(422, __('messages.inventory_hold.invalid_seat'));
        }
    }

    /**
     * Expande itens de "melhor assento disponível" (roadmap Fase 3): um item
     * sem `seat_uuid` mas com `sector_name` para um ticket type de evento com
     * assento vira N itens individuais (quantity=1 cada), com o assento já
     * escolhido por `SeatAllocationService`. Itens que já informam
     * `seat_uuid`, ou que não são de assento, passam direto.
     */
    private function expandAutoSelectSeatItems(int $tenantId, Event $event, Collection $items): Collection
    {
        return $items->flatMap(function (array $item) use ($tenantId, $event) {
            if (! empty($item['seat_uuid']) || empty($item['sector_name']) || empty($item['ticket_type_uuid'])) {
                return [$item];
            }

            $ticketType = TicketType::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $event->id)
                ->where('uuid', $item['ticket_type_uuid'])
                ->whereNull('deleted_at')
                ->with('event')
                ->first();

            if (! $ticketType || ! in_array($ticketType->event?->type, ['assento', 'mesa', 'misto'], true)) {
                return [$item];
            }

            $quantity = (int) $item['quantity'];
            $availableSeats = $this->lockAvailableSeatsInSector($event, (string) $item['sector_name']);
            $selected = $this->seatAllocationService->selectBest($availableSeats, $quantity);

            if ($selected->count() < $quantity) {
                abort(422, __('messages.inventory_hold.insufficient_availability'));
            }

            return $selected->map(fn (Seat $seat) => [
                'ticket_type_uuid' => $item['ticket_type_uuid'],
                'seat_uuid' => $seat->uuid,
                'quantity' => 1,
            ])->all();
        });
    }

    private function lockAvailableSeatsInSector(Event $event, string $sectorName): Collection
    {
        if (! $event->venueMapVersion) {
            return collect();
        }

        return Seat::query()
            ->where('venue_map_version_id', $event->venueMapVersion->id)
            ->where('sector_name', $sectorName)
            ->where('kind', 'assento')
            ->where('status', 'disponivel')
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->get()
            ->filter(fn (Seat $seat) => $this->calculateAvailableUnits(null, null, $seat, null) > 0)
            ->values();
    }

    private function assertNoDuplicateSeats(Collection $items): void
    {
        $seatUuids = $items->pluck('seat_uuid')->filter()->values();

        if ($seatUuids->count() !== $seatUuids->unique()->count()) {
            abort(422, __('messages.inventory_hold.duplicate_seat'));
        }
    }

    private function seatRequiresExclusiveReservation(Seat $seat): bool
    {
        return in_array($seat->kind, ['mesa', 'camarote'], true);
    }

    private function lockTicketType(int $tenantId, int $eventId, string $uuid): TicketType
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('uuid', $uuid)
            ->where('status', 'ativo')
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->with(['event', 'session', 'batches' => fn ($q) => $q->whereNull('deleted_at')->orderBy('priority')])
            ->firstOrFail();
    }

    private function lockEventProduct(int $tenantId, int $eventId, string $uuid): EventProduct
    {
        return EventProduct::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('uuid', $uuid)
            ->where('status', 'ativo')
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockSeatForEvent(Event $event, string $uuid): Seat
    {
        if (! $event->venueMapVersion) {
            abort(422, __('messages.inventory_hold.invalid_seat'));
        }

        return Seat::query()
            ->where('venue_map_version_id', $event->venueMapVersion->id)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function resolveAccessibleHold(int $tenantId, string $holdUuid, ?string $sessionToken, ?FinalCustomer $customer = null, bool $lock = false): InventoryHold
    {
        $query = InventoryHold::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $holdUuid)
            ->whereNull('deleted_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        $hold = $query->firstOrFail();

        $customerMatches = $customer && (int) $hold->final_customer_id === (int) $customer->id;
        $sessionMatches = $sessionToken !== null && $hold->session_token === $sessionToken;

        if (! $customerMatches && ! $sessionMatches) {
            abort(404);
        }

        return $hold;
    }

    private function loadHold(InventoryHold $hold): InventoryHold
    {
        return $hold->load([
            'event',
            'session',
            'items.ticketType',
            'items.eventProduct',
            'items.seat',
            'items.ticketBatch',
        ]);
    }

    private function expireHolds(int $tenantId, ?int $eventId = null): void
    {
        InventoryHold::query()
            ->where('tenant_id', $tenantId)
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->where('status', InventoryHold::STATUS_RESERVED)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => InventoryHold::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }

    private function expireSingleHold(InventoryHold $hold): void
    {
        if ($hold->status === InventoryHold::STATUS_RESERVED && $hold->expires_at instanceof CarbonInterface && $hold->expires_at->isPast()) {
            $hold->forceFill([
                'status' => InventoryHold::STATUS_EXPIRED,
            ])->save();
        }
    }

    private function resolveEffectiveUnitPrice(TicketType|EventProduct $sellable): float
    {
        return (float) $sellable->price;
    }
}
