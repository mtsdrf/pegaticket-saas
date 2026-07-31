<?php

namespace Tests\Feature\Auth;

use App\Events\User\UserPasswordChanged;
use App\Mail\PasswordResetMail;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ana Staff',
            'email' => 'ana@test.com',
            'password' => Hash::make('oldpassword123'),
            'is_active' => true,
        ]);
    }

    private function requestResetAndCaptureToken(string $email): string
    {
        $captured = null;

        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])
            ->assertStatus(200);

        Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$captured) {
            $captured = Str::afterLast($mail->resetUrl, '/');

            return true;
        });

        return $captured;
    }

    #[Test]
    public function full_happy_path_request_then_reset_then_login_with_new_password(): void
    {
        Event::fake([UserPasswordChanged::class]);

        $token = $this->requestResetAndCaptureToken('ana@test.com');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(200);

        $this->user->refresh();
        $this->assertNull($this->user->password_reset_token_hash);
        $this->assertNull($this->user->password_reset_expires_at);
        $this->assertTrue(Hash::check('NewPassword123!', $this->user->password));

        Event::assertDispatched(UserPasswordChanged::class, fn($event) => $event->userUuid === $this->user->uuid);

        // Login com a senha antiga deixa de funcionar.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@test.com',
            'password' => 'oldpassword123',
        ])->assertStatus(401);

        // Login com a senha nova funciona.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@test.com',
            'password' => 'NewPassword123!',
        ])->assertStatus(200);
    }

    #[Test]
    public function requesting_reset_for_nonexistent_email_does_not_error_and_sends_no_mail(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@test.com'])
            ->assertStatus(200);

        Mail::assertNothingSent();
    }

    #[Test]
    public function response_does_not_reveal_whether_email_exists(): void
    {
        Mail::fake();

        $existing = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@test.com']);
        $nonexistent = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@test.com']);

        $existing->assertStatus(200);
        $nonexistent->assertStatus(200);
        $this->assertSame($existing->json('message'), $nonexistent->json('message'));
        $this->assertSame($existing->json('success'), $nonexistent->json('success'));
    }

    #[Test]
    public function expired_token_is_rejected(): void
    {
        $token = $this->requestResetAndCaptureToken('ana@test.com');

        $this->user->refresh();
        $this->user->forceFill(['password_reset_expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PASSWORD_RESET_TOKEN');

        $this->user->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $this->user->password));
    }

    #[Test]
    public function wrong_token_is_rejected(): void
    {
        $this->requestResetAndCaptureToken('ana@test.com');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PASSWORD_RESET_TOKEN');

        $this->user->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $this->user->password));
    }
}
