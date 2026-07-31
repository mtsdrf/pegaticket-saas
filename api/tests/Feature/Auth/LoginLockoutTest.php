<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\ThrottleRequests;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bloqueio de login por força bruta (roadmap 1A). Throttle desabilitado
 * aqui de propósito: a rota /auth/login tem throttle:5,1 e este teste
 * precisa de mais de 5 tentativas para exercitar o lockout de aplicação,
 * que é um mecanismo distinto do rate limit de rota.
 */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Lock User',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function five_failed_attempts_lock_the_account_for_fifteen_minutes(): void
    {
        $this->createUser('lock@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'lock@example.com',
                'password' => 'WrongPassword1!',
            ])->assertStatus(401);
        }

        $this->assertDatabaseHas('login_lockouts', ['email' => 'lock@example.com', 'failed_attempts' => 5]);

        // Mesmo com a senha CORRETA, agora está bloqueado.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'lock@example.com',
            'password' => 'Password123!',
        ])->assertStatus(401)
            ->assertJsonPath('message', __('messages.auth.account_locked'));

        // Após a janela de 15 min, o login volta a funcionar.
        $this->travel(16)->minutes();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'lock@example.com',
            'password' => 'Password123!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function successful_login_clears_previous_failed_attempts(): void
    {
        $this->createUser('reset@example.com');

        // 3 falhas (abaixo do limite).
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'reset@example.com',
                'password' => 'WrongPassword1!',
            ])->assertStatus(401);
        }

        // Login correto zera o contador.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'Password123!',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('login_lockouts', ['email' => 'reset@example.com']);
    }
}
