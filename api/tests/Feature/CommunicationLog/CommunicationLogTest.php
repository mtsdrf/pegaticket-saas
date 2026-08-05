<?php

namespace Tests\Feature\CommunicationLog;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommunicationLogTest extends TestCase
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
            'email' => 'admin@communicationlog.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@communicationlog.test',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        $this->grantPermission('communication_logs', 'read');
    }

    #[Test]
    public function it_lists_communication_logs(): void
    {
        $this->createLog(['type' => 'password_reset']);
        $this->createLog(['type' => 'ticket_delivery']);

        $this
            ->withHeader('Authorization', 'Bearer '.$this->accessToken)
            ->getJson('/api/v1/communication-logs')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        $this->createLog(['type' => 'password_reset']);
        $this->createLog(['type' => 'ticket_delivery']);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->accessToken)
            ->getJson('/api/v1/communication-logs?type=password_reset')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('password_reset', $response->json('data.0.type'));
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $this->createLog(['status' => 'sent']);
        $this->createLog(['status' => 'failed', 'error_message' => 'boom']);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->accessToken)
            ->getJson('/api/v1/communication-logs?status=failed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('failed', $response->json('data.0.status'));
        $this->assertSame('boom', $response->json('data.0.error_message'));
    }

    #[Test]
    public function it_filters_by_recipient_email(): void
    {
        $this->createLog(['recipient_email' => 'joao@example.test']);
        $this->createLog(['recipient_email' => 'maria@example.test']);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->accessToken)
            ->getJson('/api/v1/communication-logs?recipient_email=joao')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('joao@example.test', $response->json('data.0.recipient_email'));
    }

    #[Test]
    public function it_paginates_results(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createLog(['recipient_email' => "user{$i}@example.test"]);
        }

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->accessToken)
            ->getJson('/api/v1/communication-logs?per_page=2&page=1')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $pagination = $response->json('meta.pagination');

        $this->assertSame(2, $pagination['per_page']);
        $this->assertSame(5, $pagination['total']);
        $this->assertSame(3, $pagination['last_page']);
    }

    #[Test]
    public function it_requires_permission_to_list(): void
    {
        $otherUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'No Permission User',
            'email' => 'noperm@communicationlog.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'noperm@communicationlog.test',
            'password' => 'password123',
        ])->json('data');

        $this
            ->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/v1/communication-logs')
            ->assertStatus(403);
    }

    protected function createLog(array $overrides = []): void
    {
        DB::table('communication_logs')->insert(array_merge([
            'tenant_id' => null,
            'type' => 'password_reset',
            'recipient_email' => 'test@example.test',
            'status' => 'sent',
            'error_message' => null,
            'sent_at' => now(),
            'created_at' => now(),
        ], $overrides));
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group '.$functionality.' '.$action,
            'slug' => 'rbac-'.$functionality.'-'.$action,
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

        if (! $actionId) {
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
