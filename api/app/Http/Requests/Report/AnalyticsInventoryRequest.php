<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Relatório de inventário/assentos avançado (roadmap Fase A2) —
 * `event_uuid` é sempre obrigatório (regra transversal de filtro ativo por
 * padrão: aqui o filtro é o evento, não período, porque ocupação é
 * snapshot estrutural do mapa de assentos, não recorte de data).
 */
class AnalyticsInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'string', 'uuid'],
        ];
    }
}
