<?php

namespace Tests\Feature\Auth;

use App\Events\Auth\LoginSucceeded;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\TokenRefreshed;
use App\Events\Auth\LogoutSucceeded;
use App\Models\Group\Group;
use App\Models\User\User;
use App\Services\Auth\JWTService;
use Database\Seeders\ActionsSeeder;
use Database\Seeders\FunctionalitiesSeeder;
use Database\Seeders\GroupsSeeder;
use Database\Seeders\InitialPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function login_succeeds_and_dispatches_event(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'refresh_token',
                ],
            ]);

        Event::assertDispatched(LoginSucceeded::class);
    }

    #[Test]
    public function login_fails_and_dispatches_event(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);

        Event::assertDispatched(LoginFailed::class);
    }

    #[Test]
    public function refresh_succeeds_and_dispatches_event(): void
    {
        Event::fake();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ])->json('data');

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'refresh_token',
                ],
            ]);

        Event::assertDispatched(TokenRefreshed::class);
    }

    #[Test]
    public function refresh_fails_and_dispatches_event(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(401);

        Event::assertDispatched(LoginFailed::class);
    }

    #[Test]
    public function logout_succeeds_and_dispatches_event(): void
    {
        Event::fake();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ])->json('data');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);

        Event::assertDispatched(LogoutSucceeded::class);
    }

    #[Test]
    public function self_signup_creates_owner_session_scoped_to_the_new_tenant_and_preserves_scope_on_refresh(): void
    {
        $this->seed([
            ActionsSeeder::class,
            FunctionalitiesSeeder::class,
            GroupsSeeder::class,
            InitialPlansSeeder::class,
        ]);

        $usersFunctionalityId = DB::table('functionalities')->where('slug', 'users')->value('id');
        $reportsFunctionalityId = DB::table('functionalities')->where('slug', 'reports')->value('id');

        $plansResponse = $this->getJson('/api/v1/auth/signup/plans');
        $plansResponse
            ->assertStatus(200)
            ->assertJsonFragment(['slug' => 'prata'])
            ->assertJsonFragment(['is_trial_default' => true]);

        $response = $this->postJson('/api/v1/auth/signup', [
            'owner_name' => 'Tenant Owner',
            'owner_email' => 'owner@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenant_name' => 'Tenant Novo',
            'tenant_slug' => 'tenant-novo',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'refresh_token',
                    'tenant_uuid',
                ],
            ]);

        $tenantUuid = $response->json('data.tenant_uuid');
        $tenantId = DB::table('tenants')->where('uuid', $tenantUuid)->value('id');
        $userId = DB::table('users')->where('email', 'owner@test.com')->value('id');
        $ownerRoleId = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'owner')
            ->value('id');

        $this->assertNotNull($tenantId);
        $this->assertNotNull($userId);
        $this->assertNotNull($ownerRoleId);

        $this->assertDatabaseHas('group_user', [
            'user_id' => $userId,
            'group_id' => Group::where('slug', 'clients')->value('id'),
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tenant_role_id' => $ownerRoleId,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tenant_role_permissions', [
            'tenant_role_id' => $ownerRoleId,
            'functionality_id' => $usersFunctionalityId,
        ]);

        $this->assertDatabaseHas('tenant_role_permissions', [
            'tenant_role_id' => $ownerRoleId,
            'functionality_id' => $reportsFunctionalityId,
        ]);

        $jwtService = app(JWTService::class);
        $payload = $jwtService->getPayload($response->json('data.access_token'));

        $this->assertSame($tenantUuid, $payload->get('tenant_uuid'));

        $refresh = $this
            ->withHeader('Authorization', 'Bearer ' . $response->json('data.access_token'))
            ->postJson('/api/v1/auth/refresh', [
                'refresh_token' => $response->json('data.refresh_token'),
            ]);

        $refresh->assertStatus(200);

        $refreshPayload = $jwtService->getPayload($refresh->json('data.access_token'));

        $this->assertSame($tenantUuid, $refreshPayload->get('tenant_uuid'));
    }
}
