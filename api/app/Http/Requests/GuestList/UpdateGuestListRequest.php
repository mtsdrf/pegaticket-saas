<?php

namespace App\Http\Requests\GuestList;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['sometimes', 'uuid'],
            'event_session_uuid' => ['nullable', 'uuid'],
            'ticket_type_uuid' => ['sometimes', 'uuid'],
            'name' => ['sometimes', 'string', 'max:150'],
            'quantity_per_entry' => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
