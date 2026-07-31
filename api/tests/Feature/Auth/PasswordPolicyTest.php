<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUserInvite;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Política de senha forte (mín. 12, maiúscula+minúscula+número+símbolo,
 * Password::defaults() em AppServiceProvider), 1 teste por FormRequest
 * tocado. LoginRequest não é coberto aqui de propósito — login de senha já
 * existente não muda.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const WEAK_PASSWORD = 'password123';

    private function grantGlobalPermission(int $userId, string $functionality, string $action): void
    {
        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Password Policy Group ' . $action,
            'slug' => 'password-policy-group-' . $action,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $funcId = DB::table('functionalities')->where('slug', $functionality)->value('id');
        if (!$funcId) {
            $funcId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst($functionality),
                'slug' => $functionality,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $actionId = DB::table('actions')->where('key', $action)->value('id');
        if (!$actionId) {
            $actionId = DB::table('actions')->insertGetId([
                'key' => $action,
                'name' => ucfirst($action),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('group_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'functionality_id' => $funcId,
            'action_id' => $actionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function signup_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/auth/signup', [
            'owner_name' => 'Tenant Owner',
            'owner_email' => 'owner-weak@test.com',
            'password' => self::WEAK_PASSWORD,
            'password_confirmation' => self::WEAK_PASSWORD,
            'tenant_name' => 'Tenant Fraco',
            'tenant_slug' => 'tenant-fraco-' . Str::random(6),
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function signup_rejects_password_above_max_length(): void
    {
        // Achado de code review: Password::defaults() sem ->max(255) deixava
        // o backend aceitar senha de tamanho ilimitado (só o frontend
        // limitava via maxLength HTML, trivialmente contornável).
        $tooLong = 'Aa1!' . str_repeat('x', 252);

        $this->postJson('/api/v1/auth/signup', [
            'owner_name' => 'Tenant Owner',
            'owner_email' => 'owner-toolong@test.com',
            'password' => $tooLong,
            'password_confirmation' => $tooLong,
            'tenant_name' => 'Tenant Longo',
            'tenant_slug' => 'tenant-longo-' . Str::random(6),
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function accept_invite_rejects_weak_password(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Invite Tenant',
            'slug' => 'invite-tenant-weak-' . Str::random(8),
            'is_active' => true,
        ]);

        $role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Member',
            'slug' => 'member',
            'is_active' => true,
        ]);

        $token = Str::random(64);
        TenantUserInvite::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $role->id,
            'name' => 'Convidado',
            'email' => 'convidado-weak@test.com',
            'token_hash' => hash('sha512', $token),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $token,
            'password' => self::WEAK_PASSWORD,
            'password_confirmation' => self::WEAK_PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function reset_password_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'any-token',
            'password' => self::WEAK_PASSWORD,
            'password_confirmation' => self::WEAK_PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function update_password_rejects_weak_new_password(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ana Staff',
            'email' => 'ana-weak@test.com',
            'password' => Hash::make('CurrentPassword123!'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana-weak@test.com',
            'password' => 'CurrentPassword123!',
        ])->json('data');

        $this
            ->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/auth/profile/password', [
                'current_password' => 'CurrentPassword123!',
                'new_password' => self::WEAK_PASSWORD,
                'new_password_confirmation' => self::WEAK_PASSWORD,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('new_password');

        $this->assertTrue(Hash::check('CurrentPassword123!', $user->fresh()->password));
    }

    #[Test]
    public function store_user_rejects_weak_password(): void
    {
        $actor = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Actor',
            'email' => 'actor-weak@test.com',
            'password' => Hash::make('ActorPassword123!'),
            'is_active' => true,
        ]);

        $this->grantGlobalPermission($actor->id, 'users', 'create');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'actor-weak@test.com',
            'password' => 'ActorPassword123!',
        ])->json('data');

        $this
            ->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/users', [
                'name' => 'Novo Usuário',
                'email' => 'novo-weak@test.com',
                'password' => self::WEAK_PASSWORD,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function update_user_rejects_weak_password(): void
    {
        $actor = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Actor',
            'email' => 'actor-update-weak@test.com',
            'password' => Hash::make('ActorPassword123!'),
            'is_active' => true,
        ]);

        $target = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Alvo',
            'email' => 'alvo-weak@test.com',
            'password' => Hash::make('AlvoPassword123!'),
            'is_active' => true,
        ]);

        $this->grantGlobalPermission($actor->id, 'users', 'update');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'actor-update-weak@test.com',
            'password' => 'ActorPassword123!',
        ])->json('data');

        $this
            ->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->putJson('/api/v1/users/' . $target->uuid, [
                'password' => self::WEAK_PASSWORD,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
