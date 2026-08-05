<?php

namespace App\Http\Requests\Event;

use App\Models\Event\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketTypeChannelQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ticketType = $this->route('ticketType');
        $ticketTypeId = $ticketType instanceof TicketType ? $ticketType->id : null;

        return [
            'channel' => [
                'required',
                'string',
                Rule::in(['storefront', 'staff', 'affiliate']),
                Rule::unique('ticket_type_channel_quotas', 'channel')
                    ->where(fn ($q) => $q->where('ticket_type_id', $ticketTypeId))
                    ->whereNull('deleted_at'),
            ],
            'quota' => ['required', 'integer', 'min:0'],
        ];
    }
}
