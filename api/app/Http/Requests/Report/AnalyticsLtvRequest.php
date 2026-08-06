<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * LTV histórico (roadmap Fase A3, parte 2) — `group_by` define o
 * agrupamento (segmento RFM de 8 níveis ou coorte de aquisição), com
 * default `segment`. Sem período: LTV histórico é vitalício por natureza
 * (ver AnalyticsService::ltvReport()).
 */
class AnalyticsLtvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_by' => ['nullable', 'string', 'in:segment,cohort'],
        ];
    }
}
