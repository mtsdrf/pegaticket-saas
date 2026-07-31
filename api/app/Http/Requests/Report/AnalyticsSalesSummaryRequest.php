<?php

namespace App\Http\Requests\Report;

class AnalyticsSalesSummaryRequest extends AnalyticsPeriodRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'group_by' => ['nullable', 'in:day,month'],
        ]);
    }
}
