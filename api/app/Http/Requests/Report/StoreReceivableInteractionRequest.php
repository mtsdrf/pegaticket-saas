<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivableInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'installment_uuid' => ['nullable', 'uuid'],
            'interaction_type' => ['required', Rule::in(['note', 'promise', 'whatsapp'])],
            'channel' => ['nullable', Rule::in(['manual', 'phone', 'whatsapp'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'promised_amount' => ['nullable', 'numeric', 'min:0.01'],
            'promised_due_date' => ['nullable', 'date', 'required_if:interaction_type,promise'],
            'contacted_at' => ['nullable', 'date'],
        ];
    }
}
