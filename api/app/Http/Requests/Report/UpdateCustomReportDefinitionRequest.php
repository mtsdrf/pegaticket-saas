<?php

namespace App\Http\Requests\Report;

use App\Services\Report\CustomReportQueryBuilder;
use App\Support\Report\CustomReportFieldWhitelist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomReportDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'data_source' => ['sometimes', 'string', Rule::in(CustomReportFieldWhitelist::dataSources())],

            'dimensions' => ['sometimes', 'array', 'max:'.CustomReportQueryBuilder::MAX_DIMENSIONS],
            'dimensions.*' => ['string', 'max:100'],

            'metrics' => ['sometimes', 'array', 'min:1', 'max:'.CustomReportQueryBuilder::MAX_METRICS],
            'metrics.*' => ['string', 'max:100'],

            'calculated_metrics' => ['sometimes', 'array', 'max:'.CustomReportQueryBuilder::MAX_CALCULATED_METRICS],
            'calculated_metrics.*.name' => ['required_with:calculated_metrics', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]{0,49}$/'],
            'calculated_metrics.*.formula' => ['required_with:calculated_metrics', 'string', 'max:300'],

            'filters' => ['sometimes', 'array', 'max:'.CustomReportQueryBuilder::MAX_FILTERS],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
