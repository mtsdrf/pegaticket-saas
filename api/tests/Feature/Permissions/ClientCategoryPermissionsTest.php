<?php

namespace Tests\Feature\Permissions;

use App\Models\Client\ClientCategory;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ClientCategoryPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('client-category-user@test.com');
    }

    #[Test]
    public function user_without_permission_cannot_list_client_categories(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/client-categories')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_client_categories(): void
    {
        $this->grantPermission('client_categories', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/client-categories')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_create_client_category(): void
    {
        $this->grantPermission('client_categories', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/client-categories', [
                'name' => 'VIP',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_create_permission_can_create_client_category(): void
    {
        $this->grantPermission('client_categories', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/client-categories', [
                'name' => 'VIP',
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_cannot_create_client_category_with_duplicate_name(): void
    {
        $this->grantPermission('client_categories', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/client-categories', ['name' => 'VIP'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/client-categories', ['name' => 'VIP'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_NAME');
    }

    #[Test]
    public function user_cannot_update_client_category_from_another_tenant(): void
    {
        $this->grantPermission('client_categories', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignCategory = ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/client-categories/' . $foreignCategory->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_client_category_from_another_tenant(): void
    {
        $this->grantPermission('client_categories', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignCategory = ClientCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/client-categories/' . $foreignCategory->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignCategory);
    }
}
