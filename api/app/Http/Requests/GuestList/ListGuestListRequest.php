<?php

namespace App\Http\Requests\GuestList;

use Illuminate\Foundation\Http\FormRequest;

class ListGuestListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'name' => ['nullable', 'string', 'max:150'],
            'event_name' => ['nullable', 'string', 'max:255'],
            'ticket_type_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
