<?php

namespace App\Http\Requests\Report;

use App\Services\Report\CustomReportQueryBuilder;
use App\Support\Report\CustomReportFieldWhitelist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de forma/tipo/tamanho — defesa em profundidade em camada
 * anterior a App\Services\Report\CustomReportQueryBuilder::validateDefinition(),
 * que é quem de fato confere cada chave contra
 * App\Support\Report\CustomReportFieldWhitelist (a validação aqui não
 * conhece a whitelist por data_source em si, só o formato).
 */
class StoreCustomReportDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'data_source' => ['required', 'string', Rule::in(CustomReportFieldWhitelist::dataSources())],

            'dimensions' => ['sometimes', 'array', 'max:'.CustomReportQueryBuilder::MAX_DIMENSIONS],
            'dimensions.*' => ['string', 'max:100'],

            'metrics' => ['required', 'array', 'min:1', 'max:'.CustomReportQueryBuilder::MAX_METRICS],
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
