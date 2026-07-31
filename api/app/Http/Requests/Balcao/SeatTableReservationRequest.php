<?php

namespace App\Http\Requests\Balcao;

use Illuminate\Foundation\Http\FormRequest;

class SeatTableReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
