<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncProductCategoryPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prices' => ['present', 'array'],
            'prices.*.client_category_uuid' => [
                'required',
                'uuid',
                Rule::exists('client_categories', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Duplicidade de client_category_uuid no mesmo payload não é
     * representável só com regras declarativas (Rule::exists valida
     * existência, não unicidade dentro do array) — checada aqui pra dar
     * 422 claro em vez de o sync sobrescrever silenciosamente com o
     * último valor do array.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $uuids = collect($this->input('prices', []))
                ->pluck('client_category_uuid')
                ->filter();

            if ($uuids->duplicates()->isNotEmpty()) {
                $validator->errors()->add(
                    'prices',
                    __('messages.product_category_price.duplicate_category_in_payload')
                );
            }
        });
    }
}
