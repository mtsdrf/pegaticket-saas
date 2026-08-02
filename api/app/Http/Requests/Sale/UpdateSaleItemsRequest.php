<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],
            // uuid presente = item existente (atualiza); ausente = item
            // novo (cria). Mesma convenção de SaleInstallmentService::reallocate().
            'items.*.uuid' => ['nullable', 'uuid'],
            'items.*.ticket_type_uuid' => [
                'required_without:items.*.event_product_uuid',
                'nullable',
                'uuid',
                Rule::exists('ticket_types', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.event_product_uuid' => [
                'required_without:items.*.ticket_type_uuid',
                'nullable',
                'uuid',
                Rule::exists('event_products', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.ticket_type_uuid.exists' => __('messages.sale.invalid_product'),
            'items.*.event_product_uuid.exists' => __('messages.sale.invalid_product'),
        ];
    }
}
