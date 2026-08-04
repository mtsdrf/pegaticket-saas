<?php

namespace App\Services\Security;

use Carbon\Carbon;

/**
 * Proteção anti-bot básica sem fornecedor externo (roadmap Fase 7) —
 * usada nos formulários públicos de maior risco de abuso automatizado:
 * criação de hold (App\Services\Storefront\StorefrontHoldService) e
 * resgate de convite de cortesia (App\Services\GuestList\GuestListService).
 *
 * Duas camadas, ambas sinalizadas no mesmo padrão de rejeição 422 já
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
 */
class AntiBotGuardService
{
    public const MIN_FILL_SECONDS = 2;

    public function assertHuman(?string $honeypot, ?string $formRenderedAt): void
    {
        if (! empty($honeypot)) {
            abort(422, __('messages.security.suspicious_submission'));
        }

        if (empty($formRenderedAt)) {
            return;
        }

        $renderedAt = Carbon::parse($formRenderedAt);

        if ($renderedAt->diffInSeconds(now(), true) < self::MIN_FILL_SECONDS) {
            abort(422, __('messages.security.suspicious_submission'));
        }
    }
}
