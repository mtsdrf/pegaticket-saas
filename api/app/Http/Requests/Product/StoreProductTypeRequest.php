<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'product_category_uuid' => [
                'required',
                'uuid',
                Rule::exists('product_categories', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_category_uuid.exists' => __('messages.product_type.invalid_category'),
        ];
    }
}
