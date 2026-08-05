<?php

namespace App\Http\Requests\TicketTypeWaitlist;

use Illuminate\Foundation\Http\FormRequest;

class ListTicketTypeWaitlistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
