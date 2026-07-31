<?php

namespace App\Http\Requests\Pdv;

use Illuminate\Foundation\Http\FormRequest;

class SetOperatorPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,6}$/'],
        ];
    }
}
