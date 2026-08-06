<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Afinidade entre eventos (roadmap Fase A3, parte 2) — `event_uuid` sempre
 * obrigatório (mesmo padrão de AnalyticsInventoryRequest/
 * AnalyticsCompareEventsRequest: sem evento selecionado, nada é buscado).
 */
class AnalyticsEventAffinityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'string', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
