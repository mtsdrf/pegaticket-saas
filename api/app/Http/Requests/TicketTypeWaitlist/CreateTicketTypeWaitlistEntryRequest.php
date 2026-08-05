<?php

namespace App\Http\Requests\TicketTypeWaitlist;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketTypeWaitlistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_type_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'quantity_desired' => ['nullable', 'integer', 'min:1', 'max:20'],
            // Anti-bot básico (App\Services\Security\AntiBotGuardService),
            // mesmo padrão de RedeemGuestListEntryRequest/StorefrontCreateHoldRequest.
            'website' => ['nullable', 'string', 'max:255'],
            'form_rendered_at' => ['nullable', 'date'],
            // Token do widget Cloudflare Turnstile (App\Services\Security\
            // TurnstileVerificationService) — nullable pois o frontend não
            // renderiza o widget sem VITE_TURNSTILE_SITE_KEY configurado.
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
