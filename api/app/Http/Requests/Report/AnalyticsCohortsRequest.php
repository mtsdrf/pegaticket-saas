<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Coortes de retenção (roadmap Fase A3, parte 2) — `from` (mês de coorte
 * inicial) é OBRIGATÓRIO: regra transversal de filtro ativo por padrão, sem
 * mês selecionado a query pesada de coortes nunca roda (ver
 * AnalyticsService::cohortsReport()).
 */
class AnalyticsCohortsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
