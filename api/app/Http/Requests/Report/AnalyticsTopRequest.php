<?php

namespace App\Http\Requests\Report;

/**
 * Endpoints de ranking (top-products, top-clients, payment-delays):
 * período opcional + limit (default 10).
 */
class AnalyticsTopRequest extends AnalyticsPeriodRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
