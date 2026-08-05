<?php

namespace App\Http\Middleware;

use App\Services\APIResponse;
use App\Services\Security\SuspiciousIpTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttle adaptativo — camada ADICIONAL sobre o `throttle:{max},{minutes}`
 * fixo já aplicado nas rotas (não substitui). Só entra em ação quando o
 * IP já foi marcado suspeito pelo App\Services\Security\
 * SuspiciousIpTracker (rejeições recentes do AntiBotGuardService:
 * honeypot, tempo mínimo de preenchimento ou Turnstile inválido). Nesse
 * caso, aplica um limite bem mais agressivo (`$maxAttempts`/`$decaySeconds`,
 * default 3 requisições/60s) só pra aquele IP naquela rota — IPs normais
 * passam direto, sem overhead extra além de 1 leitura de cache.
 *
 * Reaproveita o mesmo padrão de cache do App\Http\Middleware\
 * ThrottleRequests existente (sem RateLimiter/Cache::increment atômico
 * dedicado, para não introduzir uma segunda abstração de throttle no
 * projeto). Escopo por IP puro (ver SuspiciousIpTracker) — endpoints
 * públicos como /loja/{slug}/... podem ser acessados pela mesma pessoa em
 * lojas de tenants diferentes.
 */
class AdaptiveThrottleMiddleware
{
    public function __construct(
        private SuspiciousIpTracker $suspiciousIpTracker,
    ) {}

    public function handle(Request $request, Closure $next, int $maxAttempts = 3, int $decaySeconds = 60): Response
    {
        $ip = $request->ip();

        if (! $this->suspiciousIpTracker->isSuspicious($ip)) {
            return $next($request);
        }

        $key = 'adaptive-throttle:'.sha1($ip.'|'.$request->path());
        $attemptsKey = $key.':attempts';
        $expiresKey = $key.':expires';

        $attempts = (int) Cache::get($attemptsKey, 0);
        $expiresAt = Cache::get($expiresKey);

        if ($expiresAt && now()->timestamp > $expiresAt) {
            Cache::forget($attemptsKey);
            Cache::forget($expiresKey);
            $attempts = 0;
            $expiresAt = null;
        }

        if ($attempts >= $maxAttempts) {
            $retryAfter = $expiresAt ? ($expiresAt - now()->timestamp) : $decaySeconds;

            return APIResponse::error(
                __('messages.throttle.too_many_attempts', [
                    'seconds' => $retryAfter,
                    'minutes' => (int) ceil($retryAfter / 60),
                ]),
                429,
                'TOO_MANY_REQUESTS',
                [],
                [
                    'retry_after_seconds' => $retryAfter,
                    'retry_after' => now()->addSeconds($retryAfter)->toIso8601String(),
                ]
            )->header('Retry-After', $retryAfter);
        }

        if (! $expiresAt) {
            $expiresAt = now()->addSeconds($decaySeconds)->timestamp;
            Cache::put($expiresKey, $expiresAt, now()->addSeconds($decaySeconds));
        }

        Cache::put($attemptsKey, $attempts + 1, now()->addSeconds($decaySeconds));

        return $next($request);
    }
}
