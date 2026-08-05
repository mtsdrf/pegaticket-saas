<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontCreateHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'max:120'],
            'session_uuid' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_uuid' => ['nullable', 'uuid'],
            'items.*.event_product_uuid' => ['nullable', 'uuid'],
            'items.*.seat_uuid' => ['nullable', 'uuid'],
            'items.*.sector_name' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // Código de rastreio do afiliado/promotor (ex: ?ref=CODIGO na
            // URL da loja pública, capturado pelo frontend e repassado aqui
            // na criação do hold — mesmo ponto da jornada onde
            // session_token já é persistido). Silenciosamente ignorado se o
            // código não existir/estiver inativo (StorefrontHoldService).
            'affiliate_code' => ['nullable', 'string', 'max:40'],
            // Anti-bot básico (roadmap Fase 7) — ver App\Services\Security\
            // AntiBotGuardService. `website` é honeypot (deve ficar vazio
            // no formulário real); `form_rendered_at` é o timestamp de
            // quando o formulário carregou, enviado pelo frontend.
            'website' => ['nullable', 'string', 'max:255'],
            'form_rendered_at' => ['nullable', 'date'],
            // Token do widget Cloudflare Turnstile (App\Services\Security\
            // TurnstileVerificationService) — nullable pois o frontend não
            // renderiza o widget sem VITE_TURNSTILE_SITE_KEY configurado.
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
