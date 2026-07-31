<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'event_uuid' => [
                'required',
                'uuid',
                Rule::exists('events', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'kind' => ['nullable', 'string', Rule::in(['addon', 'parking'])],
            'requires_plate' => ['boolean'],
            'requires_model' => ['boolean'],
            'requires_color' => ['boolean'],
            'status' => ['nullable', 'string', Rule::in(['rascunho', 'ativo', 'pausado', 'esgotado', 'encerrado'])],
        ];
    }

    public function messages(): array
    {
        return [
            'event_uuid.exists' => __('messages.event_product.invalid_event'),
        ];
    }
}
