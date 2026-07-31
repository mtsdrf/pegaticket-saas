<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketplaceOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['confirm', 'startPreparation', 'readyToPickup', 'dispatch', 'cancel'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'code' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }

    public function attributes(): array
    {
        return __('messages.validation.attributes');
    }
}
