<?php

namespace App\Repositories\Eloquent;

use App\Models\Storefront\StoreBusinessHour;
use App\Repositories\Contracts\StoreBusinessHourRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreBusinessHourRepository extends BaseRepository implements StoreBusinessHourRepositoryInterface
{
    public function __construct(StoreBusinessHour $model)
    {
        parent::__construct($model);
    }

    public function getForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderBy('day_of_week')
            ->orderBy('opens_at')
            ->get();
    }

    /**
     * Substituição em lote: sem a unique (tenant_id, day_of_week) desde a
     * migration de múltiplos turnos, o caminho mais simples e sem gotcha de
     * soft-delete é apagar fisicamente todas as linhas do tenant e reinserir
     * as novas. Roda dentro da transação já aberta por
     * StoreBusinessHoursService::replaceForTenant (não abre transação nova).
     */
    public function upsertForTenant(int $tenantId, array $days): Collection
    {
        $this->model->newQuery()->where('tenant_id', $tenantId)->forceDelete();

        $now = now();
        $actorId = Auth::id();

        $rows = array_map(function (array $day) use ($tenantId, $now, $actorId) {
            $isClosed = (bool) $day['is_closed'];

            return [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'day_of_week' => $day['day_of_week'],
                'opens_at' => $isClosed ? null : ($day['opens_at'] ?? null),
                'closes_at' => $isClosed ? null : ($day['closes_at'] ?? null),
                'is_closed' => $isClosed,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $days);

        if (!empty($rows)) {
            $this->model->insert($rows);
        }

        return $this->getForTenant($tenantId);
    }
}
