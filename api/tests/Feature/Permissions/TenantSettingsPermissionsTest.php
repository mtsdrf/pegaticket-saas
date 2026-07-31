<?php

namespace Tests\Feature\Permissions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantSettingsPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('tenant-settings-perms-user@test.com');
    }

    #[Test]
    public function user_without_permission_cannot_view_tenant_settings(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_view_tenant_settings(): void
    {
        $this->grantPermission('tenant_settings', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_read_permission_cannot_update_tenant_settings(): void
    {
        $this->grantPermission('tenant_settings', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', ['send_tracking_link_whatsapp' => true])
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_update_permission_can_update_tenant_settings(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [
                'send_tracking_link_whatsapp' => true,
                'block_order_without_stock' => false,
            ])
            ->assertStatus(200);
    }
}
