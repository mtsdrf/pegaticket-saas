<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalePaymentChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['nullable', 'string', Rule::in(['pix', 'credit_card', 'debit_card'])],
            'payer_tax_id' => ['nullable', 'string', 'max:14'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email:rfc', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:20'],

            'card' => ['nullable', 'array'],
            'card.encrypted' => [
                'nullable',
                'string',
                'max:8192',
                Rule::requiredIf(fn () => in_array($this->input('method'), ['credit_card', 'debit_card'], true)),
            ],
            'card.holder_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => in_array($this->input('method'), ['credit_card', 'debit_card'], true)),
            ],
            'card.holder_tax_id' => [
                'nullable',
                'string',
                'max:14',
                Rule::requiredIf(fn () => in_array($this->input('method'), ['credit_card', 'debit_card'], true)),
            ],
            'card.installments' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
                Rule::requiredIf(fn () => $this->input('method') === 'credit_card'),
            ],
            'card.buyer_interest_total' => ['nullable', 'integer', 'min:0'],
            'card.buyer_interest_installments' => ['nullable', 'integer', 'min:0', 'max:12'],

            'authentication_method' => ['nullable', 'array'],
            'authentication_method.type' => [
                'nullable',
                'string',
                Rule::in(['THREEDS', 'INAPP']),
                Rule::requiredIf(fn () => $this->input('method') === 'debit_card'),
            ],
            'authentication_method.id' => [
                'nullable',
                'string',
                'max:80',
                Rule::requiredIf(fn () => $this->input('method') === 'debit_card'),
            ],
            'authentication_method.cavv' => [
                'nullable',
                'string',
                'max:80',
            ],
            'authentication_method.eci' => [
                'nullable',
                'string',
                'max:2',
            ],
            'authentication_method.version' => [
                'nullable',
                'string',
                'max:10',
            ],
            'authentication_method.status' => [
                'nullable',
                'string',
                'max:80',
            ],
            'authentication_method.xid' => ['nullable', 'string', 'max:80'],
            'authentication_method.dstrans_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
