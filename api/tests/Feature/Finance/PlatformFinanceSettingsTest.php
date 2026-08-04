<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\PlatformFinanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class PlatformFinanceSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('platform-finance@test.com');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function show_creates_and_returns_default_platform_finance_settings(): void
    {
        $this->grantPermission('payment_admin', 'read');

        $this->auth()
            ->getJson('/api/v1/finance/platform-settings')
            ->assertStatus(200)
            ->assertJsonPath('data.platform_fee_fixed_amount', 0)
            ->assertJsonPath('data.default_settlement_offset_days', 1)
            ->assertJsonPath('data.settlement_reference', 'event_end')
            ->assertJsonPath('data.split_custody_enabled', true)
            ->assertJsonPath('data.extra_reserve_enabled', false)
            ->assertJsonPath('data.extra_reserve_percentage', 5)
            ->assertJsonPath('data.extra_reserve_release_offset_days', 30)
            ->assertJsonPath('data.pagbank_primary_account_id', null);

        $this->assertDatabaseCount('platform_finance_settings', 1);
    }

    #[Test]
    public function update_persists_the_global_platform_finance_configuration(): void
    {
        $this->grantPermission('payment_admin', 'update');

        $this->auth()
            ->putJson('/api/v1/finance/platform-settings', [
                'platform_fee_fixed_amount' => 7.5,
                'default_settlement_offset_days' => 1,
                'settlement_reference' => 'event_end',
                'split_custody_enabled' => true,
                'extra_reserve_enabled' => true,
                'extra_reserve_percentage' => 8,
                'extra_reserve_release_offset_days' => 45,
                'pagbank_primary_account_id' => 'ACCO_PLATFORM_123',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.platform_fee_fixed_amount', 7.5)
            ->assertJsonPath('data.extra_reserve_percentage', 8)
            ->assertJsonPath('data.extra_reserve_release_offset_days', 45)
            ->assertJsonPath('data.pagbank_primary_account_id', 'ACCO_PLATFORM_123');

        $settings = PlatformFinanceSettings::query()->firstOrFail();
        $this->assertSame('7.50', $settings->platform_fee_fixed_amount);
        $this->assertSame(1, $settings->default_settlement_offset_days);
        $this->assertSame('event_end', $settings->settlement_reference);
        $this->assertSame('8.00', $settings->extra_reserve_percentage);
        $this->assertSame(45, $settings->extra_reserve_release_offset_days);
        $this->assertSame('ACCO_PLATFORM_123', $settings->pagbank_primary_account_id);
    }
}
