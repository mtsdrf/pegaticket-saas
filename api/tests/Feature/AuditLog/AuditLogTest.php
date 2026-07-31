<?php

namespace Tests\Feature\AuditLog;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@auditlog.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@auditlog.test',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        $this->grantPermission('audit_logs', 'read');

        // O próprio login acima gera um audit_log (evento "login", via
        // WriteAuditLog) — limpa antes de cada teste pra não poluir as
        // asserções de contagem/filtro dos logs criados manualmente.
        DB::table('audit_logs')->delete();
    }

    #[Test]
    public function it_lists_audit_logs(): void
    {
        $this->createLog(['event' => 'created']);
        $this->createLog(['event' => 'updated']);

        $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_by_event_contains(): void
    {
        $this->createLog(['event' => 'created']);
        $this->createLog(['event' => 'updated']);
        $this->createLog(['event' => 'deleted']);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs?event=create')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('created', $response->json('data.0.event'));
    }

    #[Test]
    public function it_filters_by_auditable_type_contains(): void
    {
        $this->createLog(['auditable_type' => 'App\\Models\\Order\\Order']);
        $this->createLog(['auditable_type' => 'App\\Models\\Plan\\Plan']);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs?auditable_type=Order')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('App\\Models\\Order\\Order', $response->json('data.0.auditable_type'));
    }

    #[Test]
    public function it_filters_by_user_name_contains(): void
    {
        $otherUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Mateus Ferreira',
            'email' => 'mateus@auditlog.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->createLog(['user_id' => $this->userId, 'event' => 'by-admin']);
        $this->createLog(['user_id' => $otherUser->id, 'event' => 'by-mateus']);
        $this->createLog(['user_id' => null, 'event' => 'no-actor']);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs?user_name=Mateus')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('by-mateus', $response->json('data.0.event'));
        $this->assertSame('Mateus Ferreira', $response->json('data.0.user_name'));
    }

    #[Test]
    public function it_returns_null_user_name_when_actor_is_null(): void
    {
        $this->createLog(['user_id' => null, 'event' => 'system-event']);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200);

        $this->assertNull($response->json('data.0.user_name'));
    }

    #[Test]
    public function it_filters_by_created_at_range(): void
    {
        $this->createLog(['event' => 'old', 'created_at' => now()->subDays(10)]);
        $this->createLog(['event' => 'recent', 'created_at' => now()->subDay()]);
        $this->createLog(['event' => 'future', 'created_at' => now()->addDay()]);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs?created_at_min=' . now()->subDays(2)->toDateString() . '&created_at_max=' . now()->toDateString())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('recent', $response->json('data.0.event'));
    }

    #[Test]
    public function it_paginates_results(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createLog(['event' => 'evt-' . $i]);
        }

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->getJson('/api/v1/audit-logs?per_page=2&page=1')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $pagination = $response->json('meta.pagination');

        $this->assertSame(2, $pagination['per_page']);
        $this->assertSame(5, $pagination['total']);
        $this->assertSame(3, $pagination['last_page']);
    }

    protected function createLog(array $overrides = []): void
    {
        DB::table('audit_logs')->insert(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'event' => 'created',
            'auditable_type' => null,
            'auditable_id' => null,
            'route' => 'v1/test',
            'method' => 'POST',
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'old_values' => null,
            'new_values' => null,
            'meta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
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
