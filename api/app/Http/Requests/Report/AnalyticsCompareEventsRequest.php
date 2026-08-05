<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Comparação entre eventos (roadmap Fase A2, último item) — 2 a
 * `MAX_EVENTS` eventos do mesmo tenant, sem período (a base de
 * comparação é "dias desde abertura de vendas" de cada evento, não
 * data de calendário, ver AnalyticsService::compareEvents()).
 */
class AnalyticsCompareEventsRequest extends FormRequest
{
    public const MAX_EVENTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuids' => ['required', 'array', 'min:2', 'max:'.self::MAX_EVENTS],
            'event_uuids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }
}
