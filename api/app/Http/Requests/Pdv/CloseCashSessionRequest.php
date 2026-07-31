<?php

namespace App\Http\Requests\Pdv;

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
            'closing_amount_declared' => ['required', 'numeric', 'min:0'],
        ];
    }
}
