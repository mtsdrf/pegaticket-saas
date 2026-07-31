<?php

namespace Tests\Feature\TenantSettings;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('tenant-settings-user@test.com');
    }

    #[Test]
    public function show_creates_and_returns_default_settings_when_none_exist_yet(): void
    {
        $this->grantPermission('tenant_settings', 'read');

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(200);

        $response->assertJsonPath('data.send_tracking_link_whatsapp', false);
        $response->assertJsonPath('data.storefront_enabled', true);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->tenant->id,
            'send_tracking_link_whatsapp' => false,
            'storefront_enabled' => true,
        ]);
    }

    #[Test]
    public function update_persists_the_toggle(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [
                'send_tracking_link_whatsapp' => true,
                'block_order_without_stock' => false,
                'storefront_enabled' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.send_tracking_link_whatsapp', true)
            ->assertJsonPath('data.storefront_enabled', false);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->tenant->id,
            'send_tracking_link_whatsapp' => true,
            'storefront_enabled' => false,
        ]);
    }

    #[Test]
    public function update_rejects_allow_delivery_and_allow_store_pickup_both_false(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [
                'send_tracking_link_whatsapp' => true,
                'block_order_without_stock' => false,
                'allow_delivery' => false,
                'allow_store_pickup' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allow_delivery']);
    }

    #[Test]
    public function update_persists_accepted_payment_methods(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [
                'send_tracking_link_whatsapp' => false,
                'block_order_without_stock' => false,
                'accepted_payment_methods' => ['pix', 'cash', 'credit_card'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.accepted_payment_methods', ['pix', 'cash', 'credit_card']);

        $settings = TenantSettings::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertEquals(['pix', 'cash', 'credit_card'], $settings->accepted_payment_methods);
    }

    #[Test]
    public function update_rejects_invalid_payment_method(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [
                'send_tracking_link_whatsapp' => false,
                'block_order_without_stock' => false,
                'accepted_payment_methods' => ['boleto'],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function update_persists_payment_receiving_method_and_pix_key(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [
                'send_tracking_link_whatsapp' => false,
                'block_order_without_stock' => false,
                'payment_receiving_method' => 'pix_key',
                'payment_pix_key' => 'pix@empresa.com',
            ])
            ->assertStatus(200);

        $response->assertJsonPath('data.payment_receiving_method', 'pix_key');
        $response->assertJsonPath('data.payment_pix_key', 'pix@empresa.com');

        $settings = TenantSettings::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('pix_key', $settings->payment_receiving_method);
        $this->assertSame('pix@empresa.com', $settings->payment_pix_key);
        $this->assertNotSame('pix@empresa.com', $settings->getRawOriginal('payment_pix_key'));
    }

    #[Test]
    public function update_requires_boolean_field(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [])
            ->assertStatus(422);
    }

    #[Test]
    public function tenants_are_isolated_from_each_other(): void
    {
        $this->grantPermission('tenant_settings', 'read');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        TenantSettings::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'send_tracking_link_whatsapp' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(200);

        // Toggle do outro tenant não pode vazar pro tenant ativo — este
        // tenant ainda não tem linha própria, deve ver o default (false).
        $response->assertJsonPath('data.send_tracking_link_whatsapp', false);
    }

    #[Test]
    public function user_without_permission_cannot_view_settings(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(403);
    }

    #[Test]
    public function user_without_permission_cannot_update_settings(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-settings', [
                'send_tracking_link_whatsapp' => true,
                'block_order_without_stock' => false,
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function my_tenants_exposes_the_toggle_without_needing_the_dedicated_permission(): void
    {
        // Usuário sem nenhuma permissão de tenant_settings — o valor deve
        // aparecer no contexto de auth mesmo assim (ver spec: qualquer
        // usuário autenticado do tenant precisa saber o valor pra decidir
        // o texto da mensagem de WhatsApp).
        TenantSettings::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'send_tracking_link_whatsapp' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/auth/my-tenants')
            ->assertStatus(200);

        $response->assertJsonFragment(['send_tracking_link_whatsapp' => true]);
    }

    #[Test]
    public function my_tenants_defaults_to_false_when_no_settings_row_exists(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/auth/my-tenants')
            ->assertStatus(200);

        $response->assertJsonFragment(['send_tracking_link_whatsapp' => false]);
    }
}
