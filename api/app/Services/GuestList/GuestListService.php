<?php

namespace App\Services\GuestList;

use App\DTOs\GuestList\AddGuestListEntryDTO;
use App\DTOs\GuestList\CreateGuestListDTO;
use App\DTOs\GuestList\RedeemGuestListEntryDTO;
use App\DTOs\GuestList\UpdateGuestListDTO;
use App\DTOs\Sale\CreateSaleDTO;
use App\Events\GuestList\GuestListEntryRedeemed;
use App\Exceptions\GuestListEntryAlreadyRedeemedException;
use App\Models\Event\Event;
use App\Models\Event\EventSession;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\GuestList\GuestList;
use App\Models\GuestList\GuestListEntry;
use App\Services\Sale\SaleService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * "Cortesias estruturadas" (roadmap Fase 4) — organizador cria uma lista
 * por evento/sessão + tipo de ingresso, adiciona convidados (nome/e-mail),
 * cada um recebe um link individual de autoatendimento. O resgate
 * reaproveita 100% o pipeline de Sale existente (SaleService::create com
 * unit_price=0, origin='staff' pra nascer já paga) — Ticket, QR e e-mail
 * de entrega saem de graça do fluxo padrão de SalePaid.
 */
class GuestListService
{
    public function __construct(
        private SaleService $saleService,
    ) {}

    public function create(int $tenantId, CreateGuestListDTO $dto): GuestList
    {
        $event = Event::where('tenant_id', $tenantId)->where('uuid', $dto->eventUuid)->firstOrFail();
        $ticketType = TicketType::where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('uuid', $dto->ticketTypeUuid)
            ->firstOrFail();
        $session = $dto->eventSessionUuid
            ? EventSession::where('tenant_id', $tenantId)->where('event_id', $event->id)->where('uuid', $dto->eventSessionUuid)->firstOrFail()
            : null;

        return GuestList::create([
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'event_session_id' => $session?->id,
            'ticket_type_id' => $ticketType->id,
            'name' => $dto->name,
            'quantity_per_entry' => max(1, $dto->quantityPerEntry),
            'notes' => $dto->notes,
        ]);
    }

    public function paginate(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = GuestList::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['event', 'session', 'ticketType'])
            ->withCount(['entries', 'entries as redeemed_entries_count' => fn ($q) => $q->whereNotNull('redeemed_at')])
            ->orderByDesc('created_at');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (!empty($filters['event_name'])) {
            $query->whereHas('event', fn ($q) => $q->where('name', 'like', '%'.$filters['event_name'].'%'));
        }

        if (!empty($filters['ticket_type_name'])) {
            $query->whereHas('ticketType', fn ($q) => $q->where('name', 'like', '%'.$filters['ticket_type_name'].'%'));
        }

        return $query->paginate($perPage);
    }

    public function find(int $tenantId, string $uuid): GuestList
    {
        return GuestList::where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->with(['event', 'session', 'ticketType', 'entries'])
            ->firstOrFail();
    }

    public function addEntry(int $tenantId, GuestList $guestList, AddGuestListEntryDTO $dto): GuestListEntry
    {
        $this->assertBelongsToTenant($guestList, $tenantId);

        return GuestListEntry::create([
            'tenant_id' => $tenantId,
            'guest_list_id' => $guestList->id,
            'name' => $dto->name,
            'email' => $dto->email,
            'document' => $dto->document,
        ]);
    }

    public function update(int $tenantId, GuestList $guestList, UpdateGuestListDTO $dto): GuestList
    {
        $this->assertBelongsToTenant($guestList, $tenantId);

        return DB::transaction(function () use ($tenantId, $guestList, $dto) {
            $eventId = $guestList->event_id;

            if ($dto->eventUuidProvided && $dto->eventUuid) {
                $eventId = Event::where('tenant_id', $tenantId)->where('uuid', $dto->eventUuid)->firstOrFail()->id;
            }

            $data = [];

            if ($dto->name !== null) {
                $data['name'] = $dto->name;
            }

            if ($dto->quantityPerEntry !== null) {
                $data['quantity_per_entry'] = max(1, $dto->quantityPerEntry);
            }

            if ($dto->notesProvided) {
                $data['notes'] = $dto->notes;
            }

            if ($dto->eventUuidProvided && $dto->eventUuid) {
                $data['event_id'] = $eventId;
            }

            if ($dto->ticketTypeUuidProvided && $dto->ticketTypeUuid) {
                $data['ticket_type_id'] = TicketType::where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('uuid', $dto->ticketTypeUuid)
                    ->firstOrFail()
                    ->id;
            }

            if ($dto->eventSessionUuidProvided) {
                $data['event_session_id'] = $dto->eventSessionUuid
                    ? EventSession::where('tenant_id', $tenantId)
                        ->where('event_id', $eventId)
                        ->where('uuid', $dto->eventSessionUuid)
                        ->firstOrFail()
                        ->id
                    : null;
            }

            $guestList->fill($data);
            $guestList->save();

            return $guestList->fresh(['event', 'session', 'ticketType']);
        });
    }

    public function delete(int $tenantId, GuestList $guestList): void
    {
        $this->assertBelongsToTenant($guestList, $tenantId);
        $guestList->delete();
    }

    public function findByToken(string $token): GuestListEntry
    {
        return GuestListEntry::where('token', $token)
            ->whereNull('deleted_at')
            ->with(['guestList.event', 'guestList.session', 'guestList.ticketType'])
            ->firstOrFail();
    }

    /**
     * Autoatendimento público (sem login) — cria/reaproveita o FinalCustomer
     * pelo e-mail informado, garante o vínculo confirmado com o tenant
     * (mesmo padrão de StorefrontCheckoutService::ensureCustomerLink()) e
     * emite a venda de cortesia via SaleService::create().
     */
    public function redeem(string $token, RedeemGuestListEntryDTO $dto): GuestListEntry
    {
        // Checagem otimista antes da transação (404 rápido pra token
        // inexistente sem travar linha à toa) — a checagem que realmente
        // vale, contra corrida de duas requisições simultâneas pro mesmo
        // convite (endpoint público, sem login), é a de dentro da
        // transação com lockForUpdate() logo abaixo.
        $this->findByToken($token);

        return DB::transaction(function () use ($token, $dto) {
            $entry = GuestListEntry::where('token', $token)
                ->whereNull('deleted_at')
                ->with(['guestList.event', 'guestList.session', 'guestList.ticketType'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->redeemed_at !== null) {
                throw new GuestListEntryAlreadyRedeemedException(__('messages.guest_list.already_redeemed'));
            }

            $guestList = $entry->guestList;
            $tenantId = (int) $guestList->tenant_id;

            app()->instance('tenant_id', $tenantId);

            $customer = FinalCustomer::firstOrCreate(
                ['email' => $dto->email],
                ['name' => $dto->name],
            );

            $link = FinalCustomerTenantLink::where('final_customer_id', $customer->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $link) {
                FinalCustomerTenantLink::create([
                    'final_customer_id' => $customer->id,
                    'tenant_id' => $tenantId,
                    'is_trusted' => false,
                    'is_active' => true,
                    'confirmed_at' => now(),
                ]);
            }

            $sale = $this->saleService->create(new CreateSaleDTO(
                tenantId: $tenantId,
                finalCustomerUuid: $customer->uuid,
                isInstallment: false,
                installmentsCount: null,
                notes: 'Cortesia — lista de convidados: '.$guestList->name,
                items: [[
                    'ticket_type_uuid' => $guestList->ticketType->uuid,
                    'quantity' => $guestList->quantity_per_entry,
                    'unit_price' => 0,
                    'attendee_data' => [['name' => $dto->name, 'document' => $dto->document]],
                ]],
                origin: 'staff',
            ));

            $entry->name = $dto->name;
            $entry->email = $dto->email;
            $entry->document = $dto->document;
            $entry->sale_id = $sale->id;
            $entry->redeemed_at = now();
            $entry->save();

            event(new GuestListEntryRedeemed(
                guestListEntryUuid: $entry->uuid,
                saleUuid: $sale->uuid,
            ));

            return $entry;
        });
    }

    private function assertBelongsToTenant(GuestList $guestList, int $tenantId): void
    {
        if ((int) $guestList->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
