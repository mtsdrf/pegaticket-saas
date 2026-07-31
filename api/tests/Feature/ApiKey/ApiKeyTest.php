<?php

namespace Tests\Feature\ApiKey;

use App\Models\ApiKey\TenantApiKey;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('api-key-user@test.com');
        $this->grantPermission('api-access', 'read');
        $this->grantPermission('api-access', 'create');
        $this->grantPermission('api-access', 'delete');
        $this->grantPermission('orders', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function creates_an_api_key_and_returns_plain_key_only_once(): void
    {
        $response = $this->auth()->postJson('/api/v1/api-keys', [
            'name' => 'Integração ERP',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Integração ERP');

        $plainKey = $response->json('data.key');
        $this->assertNotNull($plainKey);
        $this->assertStringStartsWith('mk_live_', $plainKey);

        $this->assertDatabaseCount('tenant_api_keys', 1);
        $stored = TenantApiKey::first();
        $this->assertNotEquals($plainKey, $stored->key_hash);

        // Listagem nunca expõe a chave nem o hash.
        $list = $this->auth()->getJson('/api/v1/api-keys');
        $list->assertStatus(200)
            ->assertJsonMissingPath('data.0.key')
            ->assertJsonMissingPath('data.0.key_hash');
    }

    #[Test]
    public function revokes_an_api_key(): void
    {
        $create = $this->auth()->postJson('/api/v1/api-keys', ['name' => 'Integração ERP']);
        $uuid = $create->json('data.uuid');
        $plainKey = $create->json('data.key');

        $this->auth()->deleteJson("/api/v1/api-keys/{$uuid}")->assertStatus(204);

        $this->assertNotNull(TenantApiKey::where('uuid', $uuid)->first()->revoked_at);

        // Chave revogada não autentica mais na API pública.
        $this->withHeader('Authorization', 'Bearer ' . $plainKey)
            ->getJson('/api/v1/public/orders')
            ->assertStatus(401)
            ->assertJsonPath('code', 'API_KEY_INVALID');
    }

    #[Test]
    public function public_api_rejects_missing_or_invalid_key(): void
    {
        // setUpTenantScopedUser() usa $this->withHeader('Authorization', ...)
        // internamente (troca de tenant), o que fica "grudado" como header
        // padrão para o resto do teste (mesmo raciocínio de MakesHttpRequests
        // ::$defaultHeaders) — sem isso, esta requisição carregaria o JWT do
        // setUp() e nunca testaria o caso "sem header nenhum".
        $this->withoutHeader('Authorization')
            ->getJson('/api/v1/public/orders')
            ->assertStatus(401)
            ->assertJsonPath('code', 'API_KEY_MISSING');

        $this->withHeader('Authorization', 'Bearer mk_live_invalid')
            ->getJson('/api/v1/public/orders')
            ->assertStatus(401)
            ->assertJsonPath('code', 'API_KEY_INVALID');
    }

    #[Test]
    public function public_api_authenticates_via_key_and_isolates_tenants(): void
    {
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $create = $this->auth()->postJson('/api/v1/api-keys', ['name' => 'Integração ERP']);
        $plainKey = $create->json('data.key');

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainKey)
            ->getJson('/api/v1/public/orders');

        $response->assertStatus(200)->assertJsonCount(1, 'data');

        // Segundo tenant, com sua própria API key, nunca vê nada do primeiro.
        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherRole = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Member',
            'slug' => 'member',
            'is_active' => true,
        ]);

        TenantUser::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->userId,
            'tenant_role_id' => $otherRole->id,
            'is_active' => true,
        ]);

        $otherKey = TenantApiKey::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Key',
            'key_hash' => hash('sha512', 'mk_live_other_plain_key'),
        ]);

        $this->withHeader('Authorization', 'Bearer mk_live_other_plain_key')
            ->getJson('/api/v1/public/orders')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->assertNotEquals($this->tenant->id, $otherTenant->id);
        $this->assertNotNull($otherKey->id);
    }
}
