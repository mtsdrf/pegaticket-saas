<?php

namespace App\Http\Requests\Balcao;

use Illuminate\Foundation\Http\FormRequest;

class SeatTableWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_uuid' => ['required', 'uuid'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
