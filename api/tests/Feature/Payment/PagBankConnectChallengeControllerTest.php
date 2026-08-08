<?php

namespace Tests\Feature\Payment;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rota pública do Connect Challenge (roadmap R2.7) — o PagBank busca esta
 * URL sem autenticação alguma antes de aprovar a aplicação Connect em
 * Sandbox. Nunca deve expor a chave privada.
 */
class PagBankConnectChallengeControllerTest extends TestCase
{
    #[Test]
    public function public_key_endpoint_returns_the_derived_public_key_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/pagbank-connect/public-key')
            ->assertStatus(200)
            ->assertJsonStructure(['public_key', 'created_at']);

        $publicKey = $response->json('public_key');

        $this->assertStringContainsString('BEGIN PUBLIC KEY', $publicKey);
        $this->assertStringNotContainsString('PRIVATE KEY', $publicKey);
    }
}
