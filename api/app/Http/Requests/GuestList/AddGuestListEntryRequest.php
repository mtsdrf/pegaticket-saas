<?php

namespace App\Http\Requests\GuestList;

use Illuminate\Foundation\Http\FormRequest;

class AddGuestListEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'document' => ['nullable', 'string', 'max:60'],
        ];
    }
}
