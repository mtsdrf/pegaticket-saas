<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verificação de CAPTCHA via Cloudflare Turnstile (gratuito, sem custo por
 * requisição — decisão confirmada com o usuário 2026-08-05). Camada
 * ADICIONAL sobre o anti-bot existente (App\Services\Security\
 * AntiBotGuardService), não substitui honeypot/tempo mínimo.
 *
 * Mesmo padrão de credencial opcional já usado para outros vendors
 * (ver config/services.php 'pagbank'): enquanto TURNSTILE_SECRET_KEY não
 * estiver preenchido no .env, o serviço fica em "modo desabilitado" e
 * sempre aprova (isEnabled() === false) — não bloqueia nenhum fluxo
 * público por falta de credencial ainda não fornecida pelo usuário.
 */
class TurnstileVerificationService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isEnabled(): bool
    {
        return filled(config('services.turnstile.secret_key'));
    }

    /**
     * Retorna true quando o token é válido OU quando o serviço está
     * desabilitado (sem TURNSTILE_SECRET_KEY configurado). Nunca lança
     * exceção própria — chamador decide como reagir a um retorno false.
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            Log::warning('turnstile.verify_failed', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
