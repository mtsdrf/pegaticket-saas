<?php

namespace App\Http\Requests\Balcao;

use Illuminate\Foundation\Http\FormRequest;

class CancelTableWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
