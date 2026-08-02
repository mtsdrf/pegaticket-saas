<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePortalLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_uuid' => [
                'required',
                'uuid',
                Rule::exists('sales', 'uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
