<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;

/**
 * Rastreamento leve de IPs suspeitos, base do App\Http\Middleware\Security\
 * AdaptiveThrottleMiddleware. Sem sistema de ML: só contagem de rejeições
 * recentes do AntiBotGuardService (honeypot, tempo mínimo de preenchimento
 * ou falha no Turnstile) por IP, numa janela curta, via cache padrão da
 * aplicação (mesmo mecanismo do RateLimiter).
 *
 * Escopo por IP puro, não IP+tenant: os endpoints públicos protegidos
 * (/loja/{slug}/..., /convites/{token}/resgatar) são acessados pela MESMA
 * pessoa em lojas de tenants diferentes, então o sinal de abuso pertence
 * ao IP em si — escopar por tenant deixaria um IP abusivo "resetar" a
 * suspeita só por trocar de loja.
 */
class SuspiciousIpTracker
{
    public const THRESHOLD = 3;

    public const WINDOW_MINUTES = 10;

    public function recordRejection(?string $ip): void
    {
        if (empty($ip)) {
            return;
        }

        $key = $this->cacheKey($ip);
        $count = (int) Cache::get($key, 0);

        Cache::put($key, $count + 1, now()->addMinutes(self::WINDOW_MINUTES));
    }

    public function isSuspicious(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        return (int) Cache::get($this->cacheKey($ip), 0) >= self::THRESHOLD;
    }

    private function cacheKey(string $ip): string
    {
        return 'security:suspicious-ip:'.$ip;
    }
}
