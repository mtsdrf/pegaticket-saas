<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class ListTicketResaleListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Obrigatório: é o único jeito de resolver o tenant_id nesta
            // rota do portal (sem middleware `tenant`) — ver
            // TicketResaleController::browse().
            'event_uuid' => ['required', 'string', 'uuid'],
            'event_session_uuid' => ['nullable', 'string', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
