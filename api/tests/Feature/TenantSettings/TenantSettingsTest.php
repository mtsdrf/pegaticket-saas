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

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(200);

        $response->assertJsonPath('data.storefront_enabled', true);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->tenant->id,
            'storefront_enabled' => true,
        ]);
    }

    #[Test]
    public function update_persists_the_toggle(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/tenant-settings', [
                'storefront_enabled' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.storefront_enabled', false);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->tenant->id,
            'storefront_enabled' => false,
        ]);
    }

    #[Test]
    public function update_persists_accepted_payment_methods(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/tenant-settings', [
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

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/tenant-settings', [
                'accepted_payment_methods' => ['boleto'],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function update_persists_payment_receiving_method_and_pix_key(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/tenant-settings', [
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
    public function update_persists_pagbank_direct_integration_without_exposing_the_token_back(): void
    {
        $this->grantPermission('tenant_settings', 'update');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/tenant-settings', [
                'payment_receiving_method' => 'pagbank_token',
                'pagbank_integration_mode' => 'manual_token',
                'pagbank_environment' => 'production',
                'pagbank_access_token' => 'pagbank-token-empresa-123',
                'pagbank_receiver_account_id' => 'ACCO_TENANT_123',
            ])
            ->assertStatus(200);

        $response->assertJsonPath('data.payment_receiving_method', 'pagbank_token');
        $response->assertJsonPath('data.pagbank_integration_mode', 'manual_token');
        $response->assertJsonPath('data.pagbank_environment', 'production');
        $response->assertJsonPath('data.has_pagbank_access_token', true);
        $response->assertJsonPath('data.pagbank_receiver_account_id', 'ACCO_TENANT_123');
        $response->assertJsonMissingPath('data.pagbank_access_token');

        $settings = TenantSettings::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('pagbank_token', $settings->payment_receiving_method);
        $this->assertSame('manual_token', $settings->pagbank_integration_mode);
        $this->assertSame('production', $settings->pagbank_environment);
        $this->assertSame('pagbank-token-empresa-123', $settings->pagbank_access_token);
        $this->assertSame('ACCO_TENANT_123', $settings->pagbank_receiver_account_id);
        $this->assertNotSame('pagbank-token-empresa-123', $settings->getRawOriginal('pagbank_access_token'));
    }

    #[Test]
    public function tenants_are_isolated_from_each_other(): void
    {
        $this->grantPermission('tenant_settings', 'read');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        TenantSettings::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'storefront_enabled' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(200);

        // Configuração do outro tenant não pode vazar pro tenant ativo —
        // este tenant ainda não tem linha própria, deve ver o default.
        $response->assertJsonPath('data.storefront_enabled', true);
    }

    #[Test]
    public function user_without_permission_cannot_view_settings(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/tenant-settings')
            ->assertStatus(403);
    }

    #[Test]
    public function user_without_permission_cannot_update_settings(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/tenant-settings', [
                'storefront_enabled' => false,
            ])
            ->assertStatus(403);
    }
}
