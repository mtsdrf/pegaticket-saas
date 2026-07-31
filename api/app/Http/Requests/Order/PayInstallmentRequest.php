<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class PayInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Data futura é permitida de propósito (pagamento agendado) —
            // não validar before_or_equal:today.
            'paid_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
