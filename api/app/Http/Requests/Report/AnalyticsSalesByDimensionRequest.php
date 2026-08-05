<?php

namespace App\Http\Requests\Report;

/**
 * Relatório de vendas por dimensão configurável (roadmap Fase A1) —
 * período opcional + dimensão + limit (default 10, ver AnalyticsTopRequest).
 */
class AnalyticsSalesByDimensionRequest extends AnalyticsTopRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'dimension' => ['nullable', 'in:ticket_type,client,origin'],
        ]);
    }
}
