<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateTicketFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['price', 'target_net'])],
            'amount' => ['required', 'integer', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'fee_payer' => ['required', 'string', Rule::in(['buyer', 'producer'])],
        ];
    }
}
