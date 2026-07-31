<?php

namespace App\Services\Balcao;

use App\DTOs\Balcao\OpenComandaDTO;
use App\Exceptions\Balcao\TableReservationException;
use App\Models\Balcao\Table;
use App\Models\Balcao\TableReservation;
use App\Services\Permission\PermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TableReservationService
{
    public function __construct(
        private TableAvailabilityService $availabilityService,
        private ComandaService $comandaService,
        private PermissionService $permissionService,
    ) {
    }

    public function list(int $tenantId, ?string $date = null): Collection
    {
        $targetDate = $date ? CarbonImmutable::parse($date) : now()->toImmutable();

        return TableReservation::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereDate('scheduled_for', $targetDate->toDateString())
            ->with(['table', 'seatedComanda'])
            ->orderBy('scheduled_for')
            ->get();
    }

    public function publicReservationsEnabled(int $tenantId): bool
    {
        return $this->permissionService->tenantPlanAllowsFunctionality($tenantId, 'balcao')
            && Table::query()->where('tenant_id', $tenantId)->whereNull('deleted_at')->exists();
    }

    public function create(int $tenantId, array $data, string $source = TableReservation::SOURCE_INTERNAL): TableReservation
    {
        return DB::transaction(function () use ($tenantId, $data, $source) {
            $scheduledFor = CarbonImmutable::parse($data['scheduled_for']);
            $durationMinutes = (int) ($data['duration_minutes'] ?? 120);
            $partySize = (int) $data['party_size'];

            if ($scheduledFor->lt(now()->subMinutes(5))) {
                throw new TableReservationException(__('messages.table_reservation.past_time'));
            }

            $table = $this->resolveOrAssignTable($tenantId, $scheduledFor, $durationMinutes, $partySize, $data['table_uuid'] ?? null);

            $reservation = TableReservation::create([
                'tenant_id' => $tenantId,
                'table_id' => $table->id,
                'customer_name' => trim((string) $data['customer_name']),
                'customer_phone' => isset($data['customer_phone']) ? trim((string) $data['customer_phone']) : null,
                'customer_email' => isset($data['customer_email']) ? trim((string) $data['customer_email']) : null,
                'party_size' => $partySize,
                'scheduled_for' => $scheduledFor,
                'duration_minutes' => $durationMinutes,
                'status' => TableReservation::STATUS_CONFIRMED,
                'source' => $source,
                'notes' => $data['notes'] ?? null,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            $this->availabilityService->syncTableStatus($table);

            return $reservation->load(['table', 'seatedComanda']);
        });
    }

    public function seat(int $tenantId, string $uuid, array $data = []): TableReservation
    {
        return DB::transaction(function () use ($tenantId, $uuid, $data) {
            $reservation = $this->find($tenantId, $uuid);

            if ($reservation->status !== TableReservation::STATUS_CONFIRMED) {
                throw new TableReservationException(__('messages.table_reservation.invalid_state_for_seating'));
            }

            $table = $reservation->table;
            if ($table === null) {
                throw new TableReservationException(__('messages.table_reservation.table_required_to_seat'));
            }

            $comanda = $this->comandaService->open($tenantId, OpenComandaDTO::fromArray([
                'table_uuid' => $table->uuid,
                'label' => $data['label'] ?? $reservation->customer_name,
            ]));

            $reservation->status = TableReservation::STATUS_SEATED;
            $reservation->seated_at = now();
            $reservation->seated_by = Auth::id();
            $reservation->seated_comanda_id = $comanda->id;
            $reservation->save();

            return $reservation->load(['table', 'seatedComanda']);
        });
    }

    public function cancel(int $tenantId, string $uuid, string $reason): TableReservation
    {
        return DB::transaction(function () use ($tenantId, $uuid, $reason) {
            $reservation = $this->find($tenantId, $uuid);

            if (in_array($reservation->status, TableReservation::TERMINAL_STATUSES, true)) {
                throw new TableReservationException(__('messages.table_reservation.already_finished'));
            }

            $reservation->status = TableReservation::STATUS_CANCELLED;
            $reservation->cancelled_reason = trim($reason);
            $reservation->cancelled_at = now();
            $reservation->cancelled_by = Auth::id();
            $reservation->save();

            if ($reservation->table) {
                $this->availabilityService->syncTableStatus($reservation->table);
            }

            return $reservation->load(['table', 'seatedComanda']);
        });
    }

    public function markNoShow(int $tenantId, string $uuid): TableReservation
    {
        return DB::transaction(function () use ($tenantId, $uuid) {
            $reservation = $this->find($tenantId, $uuid);

            if ($reservation->status !== TableReservation::STATUS_CONFIRMED) {
                throw new TableReservationException(__('messages.table_reservation.already_finished'));
            }

            $reservation->status = TableReservation::STATUS_NO_SHOW;
            $reservation->no_show_at = now();
            $reservation->no_show_by = Auth::id();
            $reservation->save();

            if ($reservation->table) {
                $this->availabilityService->syncTableStatus($reservation->table);
            }

            return $reservation->load(['table', 'seatedComanda']);
        });
    }

    public function availableTables(int $tenantId, string $scheduledFor, int $durationMinutes, int $partySize): Collection
    {
        return $this->availabilityService->listAvailableTables(
            $tenantId,
            CarbonImmutable::parse($scheduledFor),
            $durationMinutes,
            $partySize
        );
    }

    private function find(int $tenantId, string $uuid): TableReservation
    {
        $reservation = TableReservation::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->with(['table', 'seatedComanda'])
            ->first();

        if ($reservation === null) {
            abort(404);
        }

        return $reservation;
    }

    private function resolveOrAssignTable(
        int $tenantId,
        CarbonImmutable $scheduledFor,
        int $durationMinutes,
        int $partySize,
        ?string $tableUuid
    ): Table {
        if ($tableUuid !== null) {
            $table = Table::query()
                ->where('tenant_id', $tenantId)
                ->where('uuid', $tableUuid)
                ->whereNull('deleted_at')
                ->first();

            if ($table === null) {
                abort(404);
            }

            if ($table->seats !== null && $table->seats < $partySize) {
                throw new TableReservationException(__('messages.table_reservation.table_capacity_insufficient'));
            }

            if (!$this->availabilityService->tableIsAvailable($tenantId, $table, $scheduledFor, $durationMinutes)) {
                throw new TableReservationException(__('messages.table_reservation.no_availability'));
            }

            return $table;
        }

        $table = $this->availabilityService->listAvailableTables($tenantId, $scheduledFor, $durationMinutes, $partySize)->first();

        if (!$table) {
            throw new TableReservationException(__('messages.table_reservation.no_availability'));
        }

        return $table;
    }
}
