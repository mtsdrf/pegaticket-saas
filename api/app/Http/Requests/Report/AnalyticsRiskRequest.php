<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Relatório de antifraude (roadmap Fase A3) — período opcional (default no
 * AnalyticsService: últimos 12 meses) + limite da lista de vendas flagadas.
 */
class AnalyticsRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
