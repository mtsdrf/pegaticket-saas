<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualSettlementAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receivable_uuid' => ['nullable', 'uuid', 'required_without:settlement_uuid'],
            'settlement_uuid' => ['nullable', 'uuid', 'required_without:receivable_uuid'],
            'type' => ['required', 'string', 'in:manual_credit,manual_debit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:2000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
