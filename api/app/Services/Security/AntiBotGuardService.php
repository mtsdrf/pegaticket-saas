<?php

namespace App\Services\Security;

use Carbon\Carbon;

/**
 * Proteção anti-bot básica sem fornecedor externo (roadmap Fase 7) —
 * usada nos formulários públicos de maior risco de abuso automatizado:
 * criação de hold (App\Services\Storefront\StorefrontHoldService) e
 * resgate de convite de cortesia (App\Services\GuestList\GuestListService).
 *
 * Três camadas, todas sinalizadas no mesmo padrão de rejeição 422 já
 * usado no restante do projeto (abort(422, mensagem traduzida)) — sem
 * "sucesso falso": o projeto não tem precedente desse padrão em nenhum
 * outro fluxo público, e um 422 controlado não revela ao bot QUAL
 * verificação falhou (mensagem genérica).
 *
 * 1) Honeypot: campo `website` invisível no formulário real; só bots que
 *    preenchem tudo cegamente tendem a preencher.
 * 2) Tempo mínimo de preenchimento: `form_rendered_at` (timestamp de
 *    quando o formulário carregou, enviado pelo frontend) comparado ao
 *    momento do submit. Abaixo de MIN_FILL_SECONDS é suspeito de bot.
 *    Default técnico, não validado com o usuário.
 * 3) Cloudflare Turnstile (App\Services\Security\
 *    TurnstileVerificationService), camada ADICIONAL — não substitui as
 *    duas acima. Enquanto TURNSTILE_SECRET_KEY não estiver configurado
 *    no .env, essa camada fica em modo desabilitado e sempre passa (ver
 *    TurnstileVerificationService::isEnabled()).
 */
class AntiBotGuardService
{
    public const MIN_FILL_SECONDS = 2;

    public function __construct(
        private TurnstileVerificationService $turnstile,
        private SuspiciousIpTracker $suspiciousIpTracker,
    ) {}

    public function assertHuman(?string $honeypot, ?string $formRenderedAt, ?string $turnstileToken = null, ?string $remoteIp = null): void
    {
        if (! empty($honeypot)) {
            $this->suspiciousIpTracker->recordRejection($remoteIp);
            abort(422, __('messages.security.suspicious_submission'));
        }

        if (! empty($formRenderedAt)) {
            $renderedAt = Carbon::parse($formRenderedAt);

            if ($renderedAt->diffInSeconds(now(), true) < self::MIN_FILL_SECONDS) {
                $this->suspiciousIpTracker->recordRejection($remoteIp);
                abort(422, __('messages.security.suspicious_submission'));
            }
        }

        if (! $this->turnstile->verify($turnstileToken, $remoteIp)) {
            $this->suspiciousIpTracker->recordRejection($remoteIp);
            abort(422, __('messages.security.suspicious_submission'));
        }
    }
}
