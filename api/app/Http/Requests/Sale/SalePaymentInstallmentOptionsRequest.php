<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class SalePaymentInstallmentOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_card_bin' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'max_installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'max_installments_no_interest' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
