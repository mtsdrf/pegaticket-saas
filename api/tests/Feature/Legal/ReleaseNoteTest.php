<?php

namespace Tests\Feature\Legal;

use App\Models\Legal\ReleaseNote;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap A1.6 — release notes versionadas. Leitura (GET /release-notes)
 * é liberada pra qualquer usuário autenticado, sem perm; CRUD é restrito
 * a quem tiver a permissão global release_notes,{action} (mesmo padrão de
 * plans/functionalities — grupo administrators ganha automaticamente via
 * AdminPermissionsSeeder, não testado aqui).
 */
class ReleaseNoteTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Release Notes User',
            'email' => 'release-notes@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'release-notes@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->accessToken);
    }

    protected function grantPermission(string $functionality, string $action): void
    {
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

        $funcId = DB::table('functionalities')->where('slug', $functionality)->value('id')
            ?? DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst($functionality),
                'slug' => $functionality,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $actionId = DB::table('actions')->where('key', $action)->value('id')
            ?? DB::table('actions')->insertGetId([
                'key' => $action,
                'name' => ucfirst($action),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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
    public function any_authenticated_user_can_list_published_release_notes_without_permission(): void
    {
        ReleaseNote::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Publicada',
            'body' => 'Conteúdo publicado.',
            'published_at' => now()->subDay(),
        ]);

        ReleaseNote::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Rascunho',
            'body' => 'Ainda não publicada.',
            'published_at' => null,
        ]);

        ReleaseNote::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Agendada pro futuro',
            'body' => 'published_at no futuro não deve aparecer ainda.',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->auth()->getJson('/api/v1/release-notes');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Publicada'));
        $this->assertFalse($titles->contains('Rascunho'));
        $this->assertFalse($titles->contains('Agendada pro futuro'));
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/release-notes')->assertStatus(401);
    }

    #[Test]
    public function user_without_permission_cannot_create_release_note(): void
    {
        $this->auth()->postJson('/api/v1/release-notes', [
            'title' => 'Nova versão',
            'body' => 'Detalhes da versão.',
        ])->assertStatus(403);
    }

    #[Test]
    public function user_with_permission_can_create_update_and_delete_release_note(): void
    {
        $this->grantPermission('release_notes', 'create');
        $this->grantPermission('release_notes', 'update');
        $this->grantPermission('release_notes', 'delete');

        $created = $this->auth()->postJson('/api/v1/release-notes', [
            'title' => 'Nova versão',
            'body' => 'Detalhes da versão.',
            'version' => '2026.07',
            'published_at' => now()->toDateTimeString(),
        ])->assertStatus(201)->json('data');

        $this->assertDatabaseHas('release_notes', ['title' => 'Nova versão']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'release_note_created']);

        $updated = $this->auth()->putJson("/api/v1/release-notes/{$created['uuid']}", [
            'title' => 'Nova versão (revisada)',
            'body' => 'Detalhes atualizados.',
        ])->assertStatus(200)->json('data');

        $this->assertEquals('Nova versão (revisada)', $updated['title']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'release_note_updated']);

        $this->auth()->deleteJson("/api/v1/release-notes/{$created['uuid']}")
            ->assertStatus(204);

        $this->assertSoftDeleted('release_notes', ['uuid' => $created['uuid']]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'release_note_deleted']);
    }
}
