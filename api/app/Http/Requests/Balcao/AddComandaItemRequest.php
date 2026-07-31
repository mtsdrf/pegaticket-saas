<?php

namespace App\Http\Requests\Balcao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddComandaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_uuid' => [
                'required',
                'uuid',
                Rule::exists('products', 'uuid')
                    ->where(fn($q) => $q->where('tenant_id', app('tenant_id'))->whereNull('deleted_at')),
            ],
            'qty' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_item_uuid' => ['nullable', 'uuid'],
        ];
    }
}
