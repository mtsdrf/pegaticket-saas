<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class TransferTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendee_name' => ['required', 'string', 'max:255'],
            'attendee_document' => ['nullable', 'string', 'max:60'],
        ];
    }
}
