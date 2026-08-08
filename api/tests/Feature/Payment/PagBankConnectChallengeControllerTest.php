<?php

namespace Tests\Feature\Payment;

use Illuminate\Support\Facades\Config;
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
        $keyResource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        $this->assertNotFalse($keyResource);

        $privateKeyPem = '';
        $this->assertTrue(openssl_pkey_export($keyResource, $privateKeyPem));

        $privateKeyPath = storage_path('framework/testing/pagbank-connect-challenge-test.key');
        $directory = dirname($privateKeyPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($privateKeyPath, $privateKeyPem);
        Config::set('services.pagbank.connect_challenge_private_key_path', $privateKeyPath);

        $response = $this->getJson('/api/v1/pagbank-connect/public-key')
            ->assertStatus(200)
            ->assertJsonStructure(['public_key', 'created_at']);

        $publicKey = $response->json('public_key');

        $this->assertStringContainsString('BEGIN PUBLIC KEY', $publicKey);
        $this->assertStringNotContainsString('PRIVATE KEY', $publicKey);

        @unlink($privateKeyPath);
    }
}
