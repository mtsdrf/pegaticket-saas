<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontTableReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'],
            'scheduled_for' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:480'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
