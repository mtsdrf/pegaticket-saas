<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['total', 'parcial'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
            'refunded_at' => ['required', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'release_seats' => ['nullable', 'boolean'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            // Obrigatório só quando type=parcial — validado aqui (formato)
            // e de novo em SaleRefundService (regra de negócio: precisa
            // pertencer ao pedido e não ter sido estornado antes).
            'ticket_uuids' => ['required_if:type,parcial', 'array', 'min:1'],
            'ticket_uuids.*' => ['uuid'],
        ];
    }
}
