<?php

namespace App\Http\Requests\CashSession;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
