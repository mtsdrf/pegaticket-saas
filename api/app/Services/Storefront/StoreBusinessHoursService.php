<?php

namespace App\Services\Storefront;

use App\Events\Storefront\StoreBusinessHoursUpdated;
use App\Models\Storefront\StoreBusinessHour;
use App\Repositories\Contracts\StoreBusinessHourRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Horário de funcionamento da loja (roadmap Delivery) — suporta MÚLTIPLOS
 * turnos por dia (ex.: manhã + tarde). Não é CRUD genérico: a configuração é
 * sempre substituída em lote (get/replace). Usado tanto pela tela de
 * configuração do staff quanto pelo guard isOpenNow() do checkout público.
 */
class StoreBusinessHoursService
{
    public function __construct(
        private StoreBusinessHourRepositoryInterface $repository
    ) {
    }

    /**
     * Retorna todos os turnos configurados (podendo haver mais de um no mesmo
     * day_of_week), ordenados por dia e horário de abertura. Dia sem NENHUM
     * turno persistido vira um StoreBusinessHour em memória (não salvo) com
     * is_closed=true (fail-safe: não vender por omissão até o staff configurar
     * de verdade) — garantindo que os 7 dias sempre apareçam ao menos uma vez.
     */
    public function getForTenant(int $tenantId): Collection
    {
        $byDay = $this->repository->getForTenant($tenantId)->groupBy('day_of_week');

        return collect(range(0, 6))->flatMap(function (int $day) use ($byDay, $tenantId) {
            if ($byDay->has($day) && $byDay->get($day)->isNotEmpty()) {
                return $byDay->get($day)->values();
            }

            return collect([new StoreBusinessHour([
                'tenant_id' => $tenantId,
                'day_of_week' => $day,
                'opens_at' => null,
                'closes_at' => null,
                'is_closed' => true,
            ])]);
        })->values();
    }

    /**
     * @param array<int, array{day_of_week:int, opens_at:?string, closes_at:?string, is_closed:bool}> $days
     */
    public function replaceForTenant(int $tenantId, array $days): Collection
    {
        return DB::transaction(function () use ($tenantId, $days) {
            $updated = $this->repository->upsertForTenant($tenantId, $days);

            event(new StoreBusinessHoursUpdated(
                tenantId: $tenantId,
                actorId: Auth::id()
            ));

            return $updated;
        });
    }

    /**
     * isOpenNow() usa now() (timezone padrão da aplicação) para achar o
     * day_of_week atual (Carbon::dayOfWeek já usa 0=domingo, igual à coluna) e
     * compara a hora atual (H:i:s) contra opens_at/closes_at de TODOS os
     * turnos do dia — retorna true se cair em QUALQUER um deles.
     *
     * Suporta turno que atravessa a meia-noite (`closes_at < opens_at`, ex.:
     * abre 20:00 fecha 03:00) — precisa olhar os turnos de HOJE e de ONTEM: os
     * de HOJE cobrem "já abriu, ainda não virou o dia" (currentTime >=
     * opens_at, sem checar closes_at quando o turno cruza a meia-noite); os de
     * ONTEM cobrem "virou meia-noite, ainda dentro da madrugada" (currentTime
     * <= closes_at de ontem).
     *
     * Fechado (false) quando: não há turno configurado, is_closed=true, ou a
     * hora atual está fora de qualquer turno — fail-safe, nunca abre por
     * omissão.
     */
    public function isOpenNow(int $tenantId): bool
    {
        $now = now();
        $byDay = $this->repository->getForTenant($tenantId)->groupBy('day_of_week');
        $currentTime = $now->format('H:i:s');

        foreach ($byDay->get($now->dayOfWeek, collect()) as $shift) {
            if ($shift->is_closed || !$shift->opens_at || !$shift->closes_at) {
                continue;
            }

            $crossesMidnight = $shift->closes_at <= $shift->opens_at;

            if ($crossesMidnight) {
                if ($currentTime >= $shift->opens_at) {
                    return true;
                }
            } elseif ($currentTime >= $shift->opens_at && $currentTime <= $shift->closes_at) {
                return true;
            }
        }

        $yesterdayIndex = ($now->dayOfWeek + 6) % 7;

        foreach ($byDay->get($yesterdayIndex, collect()) as $shift) {
            if ($shift->is_closed || !$shift->opens_at || !$shift->closes_at) {
                continue;
            }

            $crossesMidnight = $shift->closes_at <= $shift->opens_at;

            if ($crossesMidnight && $currentTime <= $shift->closes_at) {
                return true;
            }
        }

        return false;
    }
}
