<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * App\Http\Middleware\AdaptiveThrottleMiddleware — camada adicional sobre
 * o throttle fixo (`throttle:{max},{minutes}`) das rotas públicas
 * sensíveis. Só ativa quando o IP já foi marcado suspeito pelo
 * App\Services\Security\SuspiciousIpTracker (SuspiciousIpTracker::
 * THRESHOLD = 3 rejeições do AntiBotGuardService numa janela curta).
 * Rota usada: /loja/{slug}/lista-espera, configurada com
 * `adaptive.throttle:3,60` (ver routes/api.php).
 */
class AdaptiveThrottleTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;

    #[Test]
    public function ip_with_many_recent_suspicious_attempts_gets_a_stricter_limit_than_default(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        $payload = [
            'ticket_type_uuid' => $ticketType->uuid,
            'name' => 'Bot',
            'email' => 'bot@test.com',
            'website' => 'https://bot-spam.example',
        ];

        // Requisições 1-3: ainda não suspeito (SuspiciousIpTracker::THRESHOLD
        // = 3), passam pelo throttle normal e falham no honeypot (422),
        // cada uma incrementando o contador de suspeita.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", $payload)
                ->assertStatus(422);
        }

        // Requisições 4-6: IP já suspeito, mas ainda dentro do limite
        // adaptativo (3 tentativas/60s) — seguem caindo no honeypot (422),
        // não no throttle adaptativo.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", $payload)
                ->assertStatus(422);
        }

        // Requisição 7: excedeu o limite adaptativo (3/60s) para este IP
        // nesta rota — bloqueada ANTES de chegar no honeypot/anti-bot.
        $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", $payload)
            ->assertStatus(429);
    }

    #[Test]
    public function ip_without_suspicious_history_is_not_affected_by_the_adaptive_limit(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", [
                'ticket_type_uuid' => $ticketType->uuid,
                'name' => 'Cliente Legítimo',
                'email' => "cliente{$i}@test.com",
                'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
            ]);

            $this->assertNotEquals(429, $response->getStatusCode());
        }
    }
}
