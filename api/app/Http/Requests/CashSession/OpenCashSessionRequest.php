<?php

namespace App\Http\Requests\CashSession;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
