<?php

namespace Tests\Feature\Permissions\Concerns;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Services\Permission\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Helper compartilhado para testes Feature de recursos tenant-scoped:
 * cria usuário + tenant + vínculo ativo (TenantUser/TenantRole) e troca
 * o token via /auth/switch-tenant para obter o claim `tenant_uuid`
 * exigido pelo middleware `tenant` (ResolveTenant).
 */
trait SetsUpTenantScopedUser
{
    protected int $userId;
    protected string $token;
    protected Tenant $tenant;
    protected TenantUser $tenantUser;
    protected TenantRole $tenantRole;

    protected function setUpTenantScopedUser(string $email, string $roleSlug = 'member', string $roleName = 'Member'): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Scoped User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->json('data');

        $baseToken = $login['access_token'];

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $role = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => $roleName,
            'slug' => $roleSlug,
            'is_active' => true,
        ]);

        $tenantUser = TenantUser::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'tenant_role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->tenantRole = $role;
        $this->tenantUser = $tenantUser;

        $switch = $this->withHeader('Authorization', 'Bearer ' . $baseToken)
            ->postJson('/api/v1/auth/switch-tenant', [
                'tenant_uuid' => $this->tenant->uuid,
            ])->json('data');

        $this->token = $switch['access_token'];
    }

    /**
     * Concede permissão via Group (padrão RBAC do projeto), igual ao
     * helper já usado em GroupPermissionsTest.
     */
    protected function grantPermission(string $functionality, string $action): void
    {
        // 'name' também é único no banco (groups.name) — precisa ser
        // randomizado igual ao slug, senão uma segunda chamada de
        // grantPermission() no mesmo teste (ex.: create + update)
        // colide com UniqueConstraintViolationException.
        $suffix = $functionality . '-' . $action . '-' . Str::random(6);

        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group ' . $suffix,
            'slug' => 'rbac-' . $suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $this->userId,
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

        app(PermissionService::class)->invalidateUser($this->userId);
    }
}
