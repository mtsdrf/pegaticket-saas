<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ResolveSettlementAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_type' => ['required', 'string', 'max:40'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
