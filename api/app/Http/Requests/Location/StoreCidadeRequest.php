<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCidadeRequest extends FormRequest
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
            'estado_uuid' => [
                'required',
                'uuid',
                Rule::exists('estados', 'uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado_uuid.exists' => __('messages.cidade.invalid_estado'),
        ];
    }
}
