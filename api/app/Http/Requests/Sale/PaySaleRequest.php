<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class PaySaleRequest extends FormRequest
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
            // Pagamento parcial (paridade com legado valor_pago) — ver
            // SaleService::pay(). Ausente ou >= total_amount: pagamento
            // total normal.
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
