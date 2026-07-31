<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBairroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'cidade_uuid' => [
                'required',
                'uuid',
                Rule::exists('cidades', 'uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cidade_uuid.exists' => __('messages.bairro.invalid_cidade'),
        ];
    }
}
