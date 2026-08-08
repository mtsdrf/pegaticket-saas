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
            ->assertJsonPath('data.pagbank_primary_account_id', null)
            ->assertJsonPath('data.service_fee_percentage', 10)
            ->assertJsonPath('data.service_fee_minimum_amount', 3)
            ->assertJsonPath('data.service_fee_rule_version', 1)
            ->assertJsonPath('data.estimated_pix_processing_percentage', null)
            ->assertJsonPath('data.estimated_card_processing_percentage_by_installment', null);

        $this->assertDatabaseCount('platform_finance_settings', 1);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'platform_fee_fixed_amount' => 7.5,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => true,
            'extra_reserve_percentage' => 8,
            'extra_reserve_release_offset_days' => 45,
            'pagbank_primary_account_id' => 'ACCO_PLATFORM_123',
            'service_fee_percentage' => 10,
            'service_fee_minimum_amount' => 3,
            'estimated_pix_processing_percentage' => 1.5,
            'estimated_card_processing_percentage_by_installment' => ['1' => 3.5, '2' => 4.2],
        ], $overrides);
    }

    #[Test]
    public function update_persists_the_global_platform_finance_configuration(): void
    {
        $this->grantPermission('payment_admin', 'update');

        $this->auth()
            ->putJson('/api/v1/finance/platform-settings', $this->basePayload())
            ->assertStatus(200)
            ->assertJsonPath('data.platform_fee_fixed_amount', 7.5)
            ->assertJsonPath('data.extra_reserve_percentage', 8)
            ->assertJsonPath('data.extra_reserve_release_offset_days', 45)
            ->assertJsonPath('data.pagbank_primary_account_id', 'ACCO_PLATFORM_123')
            ->assertJsonPath('data.service_fee_percentage', 10)
            ->assertJsonPath('data.service_fee_minimum_amount', 3)
            ->assertJsonPath('data.estimated_pix_processing_percentage', 1.5);

        $settings = PlatformFinanceSettings::query()->firstOrFail();
        $this->assertSame('7.50', $settings->platform_fee_fixed_amount);
        $this->assertSame(1, $settings->default_settlement_offset_days);
        $this->assertSame('event_end', $settings->settlement_reference);
        $this->assertSame('8.00', $settings->extra_reserve_percentage);
        $this->assertSame(45, $settings->extra_reserve_release_offset_days);
        $this->assertSame('ACCO_PLATFORM_123', $settings->pagbank_primary_account_id);
        $this->assertSame('10.00', $settings->service_fee_percentage);
        $this->assertSame('3.00', $settings->service_fee_minimum_amount);
    }

    #[Test]
    public function update_increments_service_fee_rule_version_only_when_percentage_or_minimum_changes(): void
    {
        $this->grantPermission('payment_admin', 'update');

        // Primeira mudança real (default 10/3 -> 12/5): versão sobe de 1 para 2.
        $this->auth()
            ->putJson('/api/v1/finance/platform-settings', $this->basePayload([
                'service_fee_percentage' => 12,
                'service_fee_minimum_amount' => 5,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.service_fee_rule_version', 2);

        // Update sem mudar percentual/minimo (só outro campo) não incrementa.
        $this->auth()
            ->putJson('/api/v1/finance/platform-settings', $this->basePayload([
                'service_fee_percentage' => 12,
                'service_fee_minimum_amount' => 5,
                'pagbank_primary_account_id' => 'ACCO_PLATFORM_456',
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.service_fee_rule_version', 2);

        // Só o minimo muda -> incrementa de novo.
        $this->auth()
            ->putJson('/api/v1/finance/platform-settings', $this->basePayload([
                'service_fee_percentage' => 12,
                'service_fee_minimum_amount' => 6,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.service_fee_rule_version', 3);
    }
}
