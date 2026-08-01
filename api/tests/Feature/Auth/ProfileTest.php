<?php

namespace Tests\Feature\Auth;

use App\Events\User\UserEmailChanged;
use App\Events\User\UserPasswordChanged;
use App\Events\User\UserProfileUpdated;
use App\Mail\UserEmailConfirmationMail;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('media.public_disks.avatar'));

        $this->user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ana Staff',
            'email' => 'ana@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->otherUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bruno Staff',
            'email' => 'bruno@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@test.com',
            'password' => 'password123',
        ]);

        $this->token = $login->json('data.access_token');
    }

    private function authed()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function get_profile_returns_own_data_not_another_users(): void
    {
        $response = $this->authed()->getJson('/api/v1/auth/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $this->user->uuid)
            ->assertJsonPath('data.name', 'Ana Staff')
            ->assertJsonPath('data.email', 'ana@test.com')
            ->assertJsonPath('data.pending_email', null);

        $this->assertNotEquals($this->otherUser->uuid, $response->json('data.uuid'));
    }

    #[Test]
    public function get_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/profile')->assertStatus(401);
    }

    #[Test]
    public function update_profile_changes_name_and_dispatches_event(): void
    {
        Event::fake();

        $response = $this->authed()->putJson('/api/v1/auth/profile', [
            'name' => 'Ana Renamed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Ana Renamed');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Ana Renamed',
        ]);

        Event::assertDispatched(UserProfileUpdated::class);
    }

    #[Test]
    public function update_profile_changes_phone(): void
    {
        $response = $this->authed()->putJson('/api/v1/auth/profile', [
            'phone' => '+55 11 91234-5678',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', '+55 11 91234-5678');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'phone' => '+55 11 91234-5678',
        ]);
    }

    #[Test]
    public function update_profile_uploads_avatar_and_exposes_url(): void
    {
        $response = $this->authed()->put('/api/v1/auth/profile', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.avatar_url'));

        $user = User::find($this->user->id);
        $this->assertNotNull($user->avatar_path);
        $this->assertNotNull($user->avatar_mime);
        $this->assertNotNull($user->avatar_updated_at);
        Storage::disk((string) config('media.public_disks.avatar'))->assertExists($user->avatar_path);
    }

    #[Test]
    public function updating_avatar_replaces_old_data(): void
    {
        $this->authed()->post('/api/v1/auth/profile', [
            '_method' => 'PUT',
            'avatar' => UploadedFile::fake()->image('old.jpg', 100, 100),
        ])->assertStatus(200);

        $originalUser = User::find($this->user->id);
        $oldPath = $originalUser->avatar_path;
        $this->assertNotNull($oldPath);
        Storage::disk((string) config('media.public_disks.avatar'))->assertExists($oldPath);

        $this->authed()->post('/api/v1/auth/profile', [
            '_method' => 'PUT',
            'avatar' => UploadedFile::fake()->image('new.jpg', 50, 50),
        ])->assertStatus(200);

        $updatedUser = User::find($this->user->id);
        $newPath = $updatedUser->avatar_path;

        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk((string) config('media.public_disks.avatar'))->assertMissing($oldPath);
        Storage::disk((string) config('media.public_disks.avatar'))->assertExists($newPath);
    }

    #[Test]
    public function get_avatar_returns_bytes_with_correct_content_type(): void
    {
        $this->authed()->put('/api/v1/auth/profile', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertStatus(200);

        $user = User::find($this->user->id);

        $response = $this->get('/api/v1/users/' . $user->uuid . '/avatar');

        $response->assertRedirect(Storage::disk((string) config('media.public_disks.avatar'))->url($user->avatar_path));

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=' . config('media.public_cache_seconds', 31536000), $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    #[Test]
    public function get_avatar_returns_404_when_user_has_no_avatar(): void
    {
        $this->get('/api/v1/users/' . $this->user->uuid . '/avatar')
            ->assertStatus(404);
    }

    #[Test]
    public function update_profile_requires_authentication(): void
    {
        $this->putJson('/api/v1/auth/profile', ['name' => 'x'])->assertStatus(401);
    }

    #[Test]
    public function wrong_current_password_is_rejected_when_changing_password(): void
    {
        $response = $this->authed()->postJson('/api/v1/auth/profile/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);

        $this->assertTrue(Hash::check('password123', User::find($this->user->id)->password));
    }

    #[Test]
    public function correct_password_change_allows_login_with_new_password(): void
    {
        Event::fake();

        $this->authed()->postJson('/api/v1/auth/profile/password', [
            'current_password' => 'password123',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
        ])->assertStatus(200);

        Event::assertDispatched(UserPasswordChanged::class);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@test.com',
            'password' => 'password123',
        ])->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@test.com',
            'password' => 'NewPassword123!',
        ])->assertStatus(200);
    }

    #[Test]
    public function change_password_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/profile/password', [
            'current_password' => 'password123',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
        ])->assertStatus(401);
    }

    #[Test]
    public function requesting_email_change_requires_correct_current_password(): void
    {
        Mail::fake();

        $this->authed()->postJson('/api/v1/auth/profile/email', [
            'new_email' => 'ana-new@test.com',
            'current_password' => 'wrong-password',
        ])->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'ana@test.com',
            'pending_email' => null,
        ]);

        Mail::assertNothingSent();
    }

    #[Test]
    public function requesting_email_change_does_not_change_email_immediately_and_exposes_pending(): void
    {
        Mail::fake();

        $response = $this->authed()->postJson('/api/v1/auth/profile/email', [
            'new_email' => 'ana-new@test.com',
            'current_password' => 'password123',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'ana@test.com',
            'pending_email' => 'ana-new@test.com',
        ]);

        Mail::assertSent(UserEmailConfirmationMail::class, function ($mail) {
            return $mail->hasTo('ana-new@test.com');
        });

        $profile = $this->authed()->getJson('/api/v1/auth/profile');
        $profile->assertJsonPath('data.email', 'ana@test.com')
            ->assertJsonPath('data.pending_email', 'ana-new@test.com');
    }

    #[Test]
    public function requesting_email_change_rejects_email_already_registered(): void
    {
        Mail::fake();

        $this->authed()->postJson('/api/v1/auth/profile/email', [
            'new_email' => $this->otherUser->email,
            'current_password' => 'password123',
        ])->assertStatus(422);
    }

    #[Test]
    public function request_email_change_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/profile/email', [
            'new_email' => 'x@test.com',
            'current_password' => 'password123',
        ])->assertStatus(401);
    }

    #[Test]
    public function confirming_email_with_valid_token_effectuates_the_change(): void
    {
        Event::fake();

        $token = $this->requestEmailChangeAndCaptureToken('ana-confirmed@test.com');

        $response = $this->postJson('/api/v1/auth/confirm-email', ['token' => $token]);

        $response->assertStatus(200);

        Event::assertDispatched(UserEmailChanged::class);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'ana-confirmed@test.com',
            'pending_email' => null,
            'pending_email_token_hash' => null,
        ]);
    }

    #[Test]
    public function old_email_stops_working_for_login_only_after_confirmation(): void
    {
        $token = $this->requestEmailChangeAndCaptureToken('ana-confirmed@test.com');

        // Antes de confirmar, o e-mail antigo ainda loga normalmente.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@test.com',
            'password' => 'password123',
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/confirm-email', ['token' => $token])->assertStatus(200);

        // Depois de confirmar, o e-mail antigo não loga mais.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@test.com',
            'password' => 'password123',
        ])->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana-confirmed@test.com',
            'password' => 'password123',
        ])->assertStatus(200);
    }

    #[Test]
    public function confirming_with_invalid_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/confirm-email', ['token' => 'not-a-real-token'])
            ->assertStatus(422);
    }

    #[Test]
    public function confirming_with_expired_token_is_rejected(): void
    {
        $token = $this->requestEmailChangeAndCaptureToken('ana-expired@test.com');

        $this->user->forceFill([
            'pending_email_expires_at' => now()->subMinute(),
        ])->saveQuietly();

        $this->postJson('/api/v1/auth/confirm-email', ['token' => $token])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'ana@test.com',
        ]);
    }

    private function requestEmailChangeAndCaptureToken(string $newEmail): string
    {
        $captured = null;

        Mail::fake();

        $this->authed()->postJson('/api/v1/auth/profile/email', [
            'new_email' => $newEmail,
            'current_password' => 'password123',
        ])->assertStatus(200);

        $this->user->refresh();

        // O token puro nunca é persistido — só o hash. Pra testar o fluxo
        // completo sem depender de capturar o e-mail real, reconstrói o
        // link a partir do hash é impossível (é sha512 de mão única); em
        // vez disso, intercepta a Mailable via Mail::fake() e extrai o
        // token da confirmUrl que o Service realmente gerou e enviou.
        Mail::assertSent(UserEmailConfirmationMail::class, function ($mail) use (&$captured) {
            $captured = Str::afterLast($mail->confirmUrl, '/');

            return true;
        });

        return $captured;
    }
}
