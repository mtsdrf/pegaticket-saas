<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Accounting\Concerns\CreatesAccountingFixtures;
use Tests\TestCase;

class AccountingAuthTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAccountingFixtures;

    #[Test]
    public function register_creates_office_with_totp_secret_but_disabled_login(): void
    {
        $response = $this->postJson('/api/v1/accounting/register', [
            'cnpj' => '11.222.333/0001-81',
            'company_name' => 'Contabilidade Alfa',
            'responsible_name' => 'João Contador',
            'email' => 'alfa@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['office', 'totp_secret', 'otpauth_uri']]);

        $office = AccountingOffice::where('email', 'alfa@example.com')->first();
        $this->assertNotNull($office);
        $this->assertSame('11222333000181', $office->cnpj);
        $this->assertNotNull($office->totp_secret);
        $this->assertNull($office->totp_enabled_at);
    }

    #[Test]
    public function register_accepts_alphanumeric_cnpj_and_normalizes_it(): void
    {
        $response = $this->postJson('/api/v1/accounting/register', [
            'cnpj' => 'AA.345.678/000A-29',
            'company_name' => 'Contabilidade Beta',
            'responsible_name' => 'Maria Contadora',
            'email' => 'beta-alfa@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);

        $office = AccountingOffice::where('email', 'beta-alfa@example.com')->first();
        $this->assertNotNull($office);
        $this->assertSame('AA345678000A29', $office->cnpj);
    }

    #[Test]
    public function register_rejects_duplicate_email(): void
    {
        $this->makeOffice(['email' => 'dup@example.com']);

        $this->postJson('/api/v1/accounting/register', [
            'cnpj' => '11222333000181',
            'company_name' => 'X',
            'responsible_name' => 'Y',
            'email' => 'dup@example.com',
            'password' => 'password123',
        ])->assertStatus(422)->assertJsonPath('code', 'EMAIL_ALREADY_REGISTERED');
    }

    #[Test]
    public function login_is_refused_before_totp_is_enabled(): void
    {
        $office = $this->makeOffice([
            'email' => 'pending@example.com',
            'totp_enabled_at' => null,
        ]);

        $this->postJson('/api/v1/accounting/login', [
            'email' => 'pending@example.com',
            'password' => 'password123',
            'code' => $this->currentTotp($office),
        ])->assertStatus(403)->assertJsonPath('code', 'TOTP_SETUP_REQUIRED');
    }

    #[Test]
    public function totp_confirm_enables_login_and_wrong_code_is_rejected(): void
    {
        $g = new Google2FA();
        $secret = $g->generateSecretKey();

        $office = AccountingOffice::create([
            'cnpj' => '11222333000181',
            'company_name' => 'Beta',
            'responsible_name' => 'Maria',
            'email' => 'beta@example.com',
            'password_hash' => Hash::make('password123'),
            'totp_secret' => $secret,
            'totp_enabled_at' => null,
        ]);

        // Código errado é rejeitado.
        $this->postJson('/api/v1/accounting/totp/confirm', [
            'email' => 'beta@example.com',
            'password' => 'password123',
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_TOTP');

        $this->assertNull($office->fresh()->totp_enabled_at);

        // Código correto ativa.
        $this->postJson('/api/v1/accounting/totp/confirm', [
            'email' => 'beta@example.com',
            'password' => 'password123',
            'code' => $g->getCurrentOtp($secret),
        ])->assertStatus(200);

        $this->assertNotNull($office->fresh()->totp_enabled_at);
    }

    #[Test]
    public function login_rejects_wrong_totp_and_accepts_correct_totp(): void
    {
        $office = $this->makeOffice(['email' => 'gamma@example.com']);

        $this->postJson('/api/v1/accounting/login', [
            'email' => 'gamma@example.com',
            'password' => 'password123',
            'code' => '000000',
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_TOTP');

        $this->postJson('/api/v1/accounting/login', [
            'email' => 'gamma@example.com',
            'password' => 'password123',
            'code' => $this->currentTotp($office),
        ])->assertStatus(200)->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);
    }

    #[Test]
    public function login_rejects_wrong_password(): void
    {
        $office = $this->makeOffice(['email' => 'delta@example.com']);

        $this->postJson('/api/v1/accounting/login', [
            'email' => 'delta@example.com',
            'password' => 'wrong-password',
            'code' => $this->currentTotp($office),
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    #[Test]
    public function me_returns_office_for_valid_token(): void
    {
        $office = $this->makeOffice(['email' => 'me@example.com']);

        $this->withHeader('Authorization', 'Bearer ' . $this->officeToken($office))
            ->getJson('/api/v1/accounting/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonPath('data.totp_enabled', true);
    }

    #[Test]
    public function me_is_unauthenticated_without_token(): void
    {
        $this->getJson('/api/v1/accounting/me')->assertStatus(401);
    }
}
