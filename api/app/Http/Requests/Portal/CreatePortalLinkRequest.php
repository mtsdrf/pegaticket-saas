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
            'order_uuid' => [
                'required',
                'uuid',
                Rule::exists('orders', 'uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
