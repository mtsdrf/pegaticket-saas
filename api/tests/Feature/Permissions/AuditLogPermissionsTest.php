<?php

namespace Tests\Feature\Permissions;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User',
            'email' => 'user@auditlog.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@auditlog.test',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];
    }

    #[Test]
    public function user_without_permission_cannot_list_audit_logs(): void
    {
        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_permission_can_list_audit_logs(): void
    {
        $this->grantPermission('audit_logs', 'read');

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['request_id', 'pagination'],
            ]);
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group ' . $functionality . ' ' . $action,
            'slug' => 'rbac-' . $functionality . '-' . $action,
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

        $funcId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($functionality),
            'slug' => $functionality,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
}
