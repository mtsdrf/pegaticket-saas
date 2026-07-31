<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUserInvite;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcceptTenantUserInviteTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected TenantRole $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Invite Tenant',
            'slug' => 'invite-tenant-' . Str::random(8),
            'is_active' => true,
        ]);

        $this->role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Member',
            'slug' => 'member',
            'is_active' => true,
        ]);
    }

    private function createInvite(string $token, array $overrides = []): TenantUserInvite
    {
        return TenantUserInvite::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'tenant_role_id' => $this->role->id,
            'name' => 'Convidado',
            'email' => 'convidado@test.com',
            'token_hash' => hash('sha512', $token),
            'expires_at' => now()->addDays(7),
        ], $overrides));
    }

    #[Test]
    public function valid_token_creates_user_and_returns_session(): void
    {
        $token = Str::random(64);
        $invite = $this->createInvite($token);

        $response = $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $token,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['access_token', 'token_type', 'expires_in', 'refresh_token', 'tenant_uuid'],
            ])
            ->assertJsonPath('data.tenant_uuid', $this->tenant->uuid);

        $this->assertDatabaseHas('users', ['email' => 'convidado@test.com']);

        $user = User::where('email', 'convidado@test.com')->first();

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'tenant_role_id' => $this->role->id,
        ]);

        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    #[Test]
    public function invalid_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => 'not-a-real-token',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_INVITE_TOKEN');
    }

    #[Test]
    public function expired_token_is_rejected(): void
    {
        $token = Str::random(64);
        $this->createInvite($token, ['expires_at' => now()->subDay()]);

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $token,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_INVITE_TOKEN');
    }

    #[Test]
    public function already_accepted_token_is_rejected(): void
    {
        $token = Str::random(64);
        $this->createInvite($token, ['accepted_at' => now()]);

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $token,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_INVITE_TOKEN');
    }

    #[Test]
    public function accept_is_blocked_when_email_already_has_account(): void
    {
        $token = Str::random(64);
        $this->createInvite($token);

        User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Já Existe',
            'email' => 'convidado@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $token,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'EMAIL_ALREADY_REGISTERED');
    }

    #[Test]
    public function password_confirmation_mismatch_fails_validation(): void
    {
        $token = Str::random(64);
        $this->createInvite($token);

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $token,
            'password' => 'Password123!',
            'password_confirmation' => 'different',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
