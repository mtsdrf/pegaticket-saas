<?php

namespace Tests\Feature\Tenant;

use App\Mail\TenantUserInviteMail;
use App\Models\Tenant\TenantUserInvite;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantUserInviteTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('owner@test.com');
    }

    #[Test]
    public function owner_with_permission_can_invite_new_user(): void
    {
        Mail::fake();
        $this->grantPermission('tenant_users', 'create');

        $roleUuid = \App\Models\Tenant\TenantRole::where('tenant_id', $this->tenant->id)->value('uuid');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-users/invite', [
                'name' => 'Convidado Teste',
                'email' => 'convidado@test.com',
                'role_uuid' => $roleUuid,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'convidado@test.com')
            ->assertJsonPath('data.name', 'Convidado Teste');

        $this->assertDatabaseHas('tenant_user_invites', [
            'tenant_id' => $this->tenant->id,
            'email' => 'convidado@test.com',
            'accepted_at' => null,
        ]);

        Mail::assertSent(TenantUserInviteMail::class, function ($mail) {
            return $mail->invite->email === 'convidado@test.com';
        });
    }

    #[Test]
    public function user_without_permission_cannot_invite(): void
    {
        $roleUuid = \App\Models\Tenant\TenantRole::where('tenant_id', $this->tenant->id)->value('uuid');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-users/invite', [
                'name' => 'Convidado Teste',
                'email' => 'convidado@test.com',
                'role_uuid' => $roleUuid,
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function invite_is_blocked_when_email_already_has_account(): void
    {
        $this->grantPermission('tenant_users', 'create');

        User::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Já Existe',
            'email' => 'jaexiste@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $roleUuid = \App\Models\Tenant\TenantRole::where('tenant_id', $this->tenant->id)->value('uuid');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-users/invite', [
                'name' => 'Convidado',
                'email' => 'jaexiste@test.com',
                'role_uuid' => $roleUuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'EMAIL_ALREADY_REGISTERED');
    }

    #[Test]
    public function invite_is_blocked_when_pending_invite_already_exists(): void
    {
        $this->grantPermission('tenant_users', 'create');

        $roleUuid = \App\Models\Tenant\TenantRole::where('tenant_id', $this->tenant->id)->value('uuid');

        $payload = [
            'name' => 'Convidado',
            'email' => 'pendente@test.com',
            'role_uuid' => $roleUuid,
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-users/invite', $payload)
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-users/invite', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_INVITE');
    }

    #[Test]
    public function invite_is_rejected_for_role_belonging_to_another_tenant(): void
    {
        $this->grantPermission('tenant_users', 'create');

        $otherTenant = \App\Models\Tenant\Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . \Illuminate\Support\Str::random(8),
            'is_active' => true,
        ]);

        $otherRole = \App\Models\Tenant\TenantRole::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Role',
            'slug' => 'other-role',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-users/invite', [
                'name' => 'Convidado',
                'email' => 'convidado2@test.com',
                'role_uuid' => $otherRole->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
