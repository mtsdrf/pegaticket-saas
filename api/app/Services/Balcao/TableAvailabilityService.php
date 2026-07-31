<?php

namespace App\Services\Balcao;

use App\Models\Balcao\Table;
use App\Models\Balcao\TableReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TableAvailabilityService
{
    public function listAvailableTables(
        int $tenantId,
        CarbonImmutable $scheduledFor,
        int $durationMinutes,
        int $partySize,
        ?string $excludeReservationUuid = null
    ): Collection {
        return Table::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($partySize) {
                $query->whereNull('seats')
                    ->orWhere('seats', '>=', $partySize);
            })
            ->orderByRaw('CASE WHEN seats IS NULL THEN 1 ELSE 0 END')
            ->orderBy('seats')
            ->orderBy('label')
            ->get()
            ->filter(fn (Table $table) => $this->tableIsAvailable(
                $tenantId,
                $table,
                $scheduledFor,
                $durationMinutes,
                $excludeReservationUuid
            ))
            ->values();
    }

    public function tableIsAvailable(
        int $tenantId,
        Table $table,
        CarbonImmutable $scheduledFor,
        int $durationMinutes,
        ?string $excludeReservationUuid = null
    ): bool {
        $end = $scheduledFor->addMinutes($durationMinutes);
        $reservations = TableReservation::query()
            ->where('tenant_id', $tenantId)
            ->where('table_id', $table->id)
            ->whereIn('status', TableReservation::ACTIVE_STATUSES)
            ->when($excludeReservationUuid !== null, fn ($query) => $query->where('uuid', '!=', $excludeReservationUuid))
            ->whereNull('deleted_at')
            ->get(['scheduled_for', 'duration_minutes']);

        foreach ($reservations as $reservation) {
            $reservationStart = CarbonImmutable::parse($reservation->scheduled_for);
            $reservationEnd = $reservationStart->addMinutes((int) $reservation->duration_minutes);

            if ($reservationStart->lt($end) && $reservationEnd->gt($scheduledFor)) {
                return false;
            }
        }

        return true;
    }

    public function syncTableStatus(Table $table): void
    {
        $hasOpenComanda = $table->comandas()
            ->whereIn('status', ['open', 'closing'])
            ->whereNull('deleted_at')
            ->exists();

        if ($hasOpenComanda) {
            if ($table->status !== Table::STATUS_OCCUPIED) {
                $table->status = Table::STATUS_OCCUPIED;
                $table->save();
            }

            return;
        }

        $hasUpcomingReservation = TableReservation::query()
            ->where('tenant_id', $table->tenant_id)
            ->where('table_id', $table->id)
            ->whereIn('status', TableReservation::ACTIVE_STATUSES)
            ->whereNull('deleted_at')
            ->exists();

        $nextStatus = $hasUpcomingReservation ? Table::STATUS_RESERVED : Table::STATUS_FREE;

        if ($table->status !== $nextStatus) {
            $table->status = $nextStatus;
            $table->save();
        }
    }
}
