<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valida a substituição em lote do horário de funcionamento. Suporta
 * MÚLTIPLOS turnos no mesmo day_of_week (ex.: manhã 08:00-12:00 + tarde
 * 13:00-18:00) — não há mais restrição de 7 dias distintos: dia ausente do
 * payload é tratado como fechado por omissão (fail-safe em
 * StoreBusinessHoursService::getForTenant). Regras por turno:
 *  - day_of_week 0-6, is_closed bool, opens_at/closes_at H:i obrigatórios
 *    quando não fechado.
 *  - `closes_at < opens_at` é PERMITIDO (horário que atravessa a meia-noite,
 *    ex.: abre 20:00, fecha 03:00). Só `closes_at === opens_at` é rejeitado
 *    (ambíguo).
 *  - máximo de 4 turnos por day_of_week.
 *  - dois turnos do MESMO day_of_week não podem se sobrepor.
 */
class UpdateStoreBusinessHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['present', 'array'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['required', 'boolean'],
            'days.*.opens_at' => ['required_if:days.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['required_if:days.*.is_closed,false', 'nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $days = (array) $this->input('days', []);

            foreach ($days as $index => $day) {
                if (!empty($day['is_closed'])) {
                    continue;
                }

                $opensAt = $day['opens_at'] ?? null;
                $closesAt = $day['closes_at'] ?? null;

                if ($opensAt && $closesAt && $closesAt === $opensAt) {
                    $validator->errors()->add(
                        "days.{$index}.closes_at",
                        __('messages.store_business_hours.closes_at_equal_to_opens_at')
                    );
                }
            }

            $this->validateShiftsPerDay($validator, $days);
        });
    }

    /**
     * Agrupa os turnos por day_of_week e valida, por dia: no máximo 4 turnos
     * e nenhuma sobreposição entre eles. Turnos fechados (is_closed=true) não
     * entram na contagem nem no cálculo de sobreposição.
     */
    private function validateShiftsPerDay(Validator $validator, array $days): void
    {
        $byDay = [];

        foreach ($days as $index => $day) {
            if (!empty($day['is_closed'])) {
                continue;
            }

            $dayOfWeek = $day['day_of_week'] ?? null;
            $opensAt = $day['opens_at'] ?? null;
            $closesAt = $day['closes_at'] ?? null;

            if ($dayOfWeek === null || !$opensAt || !$closesAt) {
                continue;
            }

            $byDay[$dayOfWeek][] = ['index' => $index, 'opens_at' => $opensAt, 'closes_at' => $closesAt];
        }

        foreach ($byDay as $shifts) {
            if (count($shifts) > 4) {
                foreach ($shifts as $shift) {
                    $validator->errors()->add(
                        "days.{$shift['index']}.opens_at",
                        __('messages.store_business_hours.too_many_shifts')
                    );
                }
            }

            $this->rejectOverlaps($validator, $shifts);
        }
    }

    private function rejectOverlaps(Validator $validator, array $shifts): void
    {
        $count = count($shifts);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->overlaps($shifts[$i], $shifts[$j])) {
                    $validator->errors()->add(
                        "days.{$shifts[$j]['index']}.opens_at",
                        __('messages.store_business_hours.overlapping_shifts')
                    );
                }
            }
        }
    }

    /**
     * Sobreposição em minutos. Turno que atravessa a meia-noite
     * (closes_at <= opens_at) tem o fim estendido em 24h para o cálculo.
     */
    private function overlaps(array $a, array $b): bool
    {
        [$aStart, $aEnd] = $this->toMinutes($a);
        [$bStart, $bEnd] = $this->toMinutes($b);

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function toMinutes(array $shift): array
    {
        $start = $this->minutesOfDay($shift['opens_at']);
        $end = $this->minutesOfDay($shift['closes_at']);

        if ($end <= $start) {
            $end += 24 * 60;
        }

        return [$start, $end];
    }

    private function minutesOfDay(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }
}
