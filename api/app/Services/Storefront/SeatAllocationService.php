<?php

namespace App\Services\Storefront;

use App\Models\Venue\Seat;
use Illuminate\Support\Collection;

/**
 * Seleção automática de "melhor assento disponível"/"assentos contíguos"
 * (roadmap Fase 3). O schema de `seats` só guarda posição livre no mapa
 * (pos_x/pos_y por setor, sem número de fileira/cadeira estruturado), então
 * contiguidade é inferida pela proximidade em pos_x dentro da mesma pos_y
 * (fileira): o menor espaçamento observado na fileira vira a "unidade" de
 * cadeira-a-cadeira; um vão maior que ~1.6x essa unidade é tratado como
 * corredor, quebrando o bloco.
 */
class SeatAllocationService
{
    private const MAX_GAP_MULTIPLIER = 1.6;

    /**
     * @param  Collection<int, Seat>  $availableSeats  assentos livres candidatos (mesmo setor)
     * @return Collection<int, Seat> vazio se não houver assentos livres suficientes
     */
    public function selectBest(Collection $availableSeats, int $quantity): Collection
    {
        if ($quantity < 1 || $availableSeats->count() < $quantity) {
            return collect();
        }

        $sorted = $availableSeats->sortBy([['pos_y', 'asc'], ['pos_x', 'asc']])->values();
        $byRow = $sorted->groupBy(fn (Seat $seat) => (string) $seat->pos_y);

        foreach ($byRow as $rowSeats) {
            $contiguous = $this->findContiguousRun($rowSeats->values(), $quantity);
            if ($contiguous !== null) {
                return $contiguous;
            }
        }

        $bestRow = $byRow
            ->filter(fn (Collection $rowSeats) => $rowSeats->count() >= $quantity)
            ->sortBy(fn (Collection $rowSeats) => $rowSeats->count())
            ->first();

        if ($bestRow !== null) {
            return $bestRow->values()->take($quantity);
        }

        return $sorted->take($quantity);
    }

    /**
     * @param  Collection<int, Seat>  $rowSeats  ordenados por pos_x
     */
    private function findContiguousRun(Collection $rowSeats, int $quantity): ?Collection
    {
        if ($rowSeats->count() < $quantity) {
            return null;
        }

        $gaps = [];
        for ($i = 1; $i < $rowSeats->count(); $i++) {
            $gaps[] = (float) $rowSeats[$i]->pos_x - (float) $rowSeats[$i - 1]->pos_x;
        }
        $unit = empty($gaps) ? 0.0 : min($gaps);
        $maxAllowedGap = $unit > 0 ? $unit * self::MAX_GAP_MULTIPLIER : PHP_FLOAT_MAX;

        $run = collect([$rowSeats[0]]);
        for ($i = 1; $i < $rowSeats->count(); $i++) {
            $gap = (float) $rowSeats[$i]->pos_x - (float) $rowSeats[$i - 1]->pos_x;
            $run = $gap <= $maxAllowedGap ? $run->push($rowSeats[$i]) : collect([$rowSeats[$i]]);

            if ($run->count() === $quantity) {
                return $run;
            }
        }

        return null;
    }
}
