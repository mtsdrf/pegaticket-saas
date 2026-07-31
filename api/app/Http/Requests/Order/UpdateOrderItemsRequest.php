<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
            'stock_location_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('stock_locations', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'expected_delivery_date' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],
            // uuid presente = item existente (atualiza); ausente = item
            // novo (cria). Mesma convenção de OrderInstallmentService::reallocate().
            'items.*.uuid' => ['nullable', 'uuid'],
            'items.*.product_uuid' => [
                'required',
                'uuid',
                Rule::exists('products', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.options.*.product_option_uuid' => [
                'required',
                'uuid',
                Rule::exists('product_options', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.options.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'stock_location_uuid.exists' => __('messages.order.invalid_stock_location'),
            'items.*.product_uuid.exists' => __('messages.order.invalid_product'),
        ];
    }
}
