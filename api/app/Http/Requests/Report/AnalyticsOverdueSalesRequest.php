<?php

namespace App\Http\Requests\Report;

class AnalyticsOverdueSalesRequest extends AnalyticsPeriodRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
