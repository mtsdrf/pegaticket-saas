<?php

namespace App\Http\Requests\Report;

class AnalyticsAbcRequest extends AnalyticsPeriodRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'dimension' => ['nullable', 'in:products,clients'],
        ]);
    }
}
