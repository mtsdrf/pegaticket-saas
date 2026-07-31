<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Token já gerado pelo MP.js no frontend — nunca dado de cartão
            // cru chega ao backend (PCI).
            'card_token' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
