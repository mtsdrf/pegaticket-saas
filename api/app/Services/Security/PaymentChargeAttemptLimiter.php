<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;

/**
 * Anti card-testing por VENDA (roadmap R4, gap 2.6) — camada distinta do
 * throttle por IP (`throttle:{max},{min}`/AdaptiveThrottleMiddleware): um
 * atacante testando cartões roubados tipicamente rotaciona IP, mas não
 * rotaciona a venda-alvo (mesma Sale, vários cartões). Contador simples em
 * cache, chave por `sale_uuid`, incrementado só em tentativa de cobrança
 * FRACASSADA (guard de estado inválido, operação em progresso, ou recusa do
 * provedor) — uma tentativa bem-sucedida não conta e não limpa o contador
 * (a venda já estará paga/não aceitará nova cobrança de qualquer forma).
 *
 * Aplicado às 3 rotas de payment-charge (rastreio público, portal do
 * cliente, staff autenticado) — diferente do anti-bot (honeypot/Turnstile),
 * que só faz sentido para tráfego anônimo, este limite protege a venda
 * independente de quem está autenticado.
 */
class PaymentChargeAttemptLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 600;

    public function assertNotExceeded(string $saleUuid): void
    {
        $attempts = (int) Cache::get($this->cacheKey($saleUuid), 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            abort(429, __('messages.security.payment_charge_attempts_exceeded'));
        }
    }

    public function recordFailedAttempt(string $saleUuid): void
    {
        $key = $this->cacheKey($saleUuid);
        $attempts = (int) Cache::get($key, 0);

        Cache::put($key, $attempts + 1, now()->addSeconds(self::DECAY_SECONDS));
    }

    private function cacheKey(string $saleUuid): string
    {
        return 'payment-charge-attempts:'.$saleUuid;
    }
}
