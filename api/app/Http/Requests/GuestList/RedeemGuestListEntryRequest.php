<?php

namespace App\Http\Requests\GuestList;

use Illuminate\Foundation\Http\FormRequest;

class RedeemGuestListEntryRequest extends FormRequest
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
            // Anti-bot básico (roadmap Fase 7) — ver App\Services\Security\
            // AntiBotGuardService. `website` é honeypot (deve ficar vazio
            // no formulário real); `form_rendered_at` é o timestamp de
            // quando o formulário carregou, enviado pelo frontend.
            'website' => ['nullable', 'string', 'max:255'],
            'form_rendered_at' => ['nullable', 'date'],
        ];
    }
}
