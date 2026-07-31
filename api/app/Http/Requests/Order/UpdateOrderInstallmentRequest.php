<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Substituição completa (não parcial) — mesma tripla de campos
            // de StoreOrderInstallmentRequest, contrato idêntico pro
            // frontend não lidar com 2 shapes diferentes.
            'installment_number' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
        ];
    }
}
