<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class CheckinSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['nullable', 'uuid'],
            'event_session_uuid' => ['nullable', 'uuid'],
            'gate_name' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
