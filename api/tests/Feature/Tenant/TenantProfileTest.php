<?php

namespace Tests\Feature\Tenant;

use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantProfileTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('media.public_disks.tenant'));
        $this->setUpTenantScopedUser('tenant-profile@test.com');
    }

    #[Test]
    public function owner_can_update_own_tenant_name(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Nome Atualizado',
            ]);

        $response->assertOk();
        $this->assertEquals('Nome Atualizado', $response->json('data.name'));
        $this->assertEquals(
            'Nome Atualizado',
            Tenant::whereKey($this->tenant->id)->value('name')
        );
    }

    #[Test]
    public function owner_can_update_own_tenant_logo(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/tenant-profile', [
                '_method' => 'PUT',
                'name' => 'Com Logo',
                'logo' => UploadedFile::fake()->image('logo.png', 60, 60),
            ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.logo_url'));

        $tenant = Tenant::whereKey($this->tenant->id)->first();
        $this->assertNotNull($tenant->logo_path);
        $this->assertNotNull($tenant->logo_mime);
        Storage::disk((string) config('media.public_disks.tenant'))->assertExists($tenant->logo_path);
    }

    #[Test]
    public function user_without_permission_gets_403(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Sem Permissao',
            ]);

        $response->assertStatus(403);
        $this->assertEquals(
            'Test Tenant',
            Tenant::whereKey($this->tenant->id)->value('name')
        );
    }

    #[Test]
    public function protected_fields_in_payload_are_ignored(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $otherPlan = Plan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Outro Plano',
            'slug' => 'outro-plano',
            'description' => 'x',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $originalSlug = $this->tenant->slug;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Somente Nome Muda',
                'slug' => 'slug-hackeado',
                'is_active' => false,
                'plan_uuid' => $otherPlan->uuid,
            ]);

        $response->assertOk();

        $tenant = Tenant::whereKey($this->tenant->id)->first();
        $this->assertEquals('Somente Nome Muda', $tenant->name);
        $this->assertEquals($originalSlug, $tenant->slug);
        $this->assertTrue((bool) $tenant->is_active);
        $this->assertNull($tenant->plan_id);
    }

    #[Test]
    public function owner_can_read_own_tenant_profile(): void
    {
        $this->grantPermission('tenant-profile', 'read');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-profile');

        $response->assertOk();
        $this->assertEquals($this->tenant->uuid, $response->json('data.uuid'));
        $this->assertEquals('Test Tenant', $response->json('data.name'));
    }

}
