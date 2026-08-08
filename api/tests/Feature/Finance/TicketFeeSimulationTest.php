<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\PlatformFinanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Simulador de taxa de serviço PegaTicket, exposto ao produtor (tenant)
 * via `GET .../rule` e `POST .../simulate` — sem exigir a permissão
 * `payment_admin` (exclusiva de staff da plataforma, ver
 * PlatformFinanceSettingsController).
 */
class TicketFeeSimulationTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('ticket-fee-simulation-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function tenant_user_with_ticket_types_read_can_load_the_current_rule(): void
    {
        $this->grantPermission('ticket_types', 'read');

        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 0,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => false,
            'extra_reserve_percentage' => 5,
            'extra_reserve_release_offset_days' => 30,
            'service_fee_percentage' => 10.00,
            'service_fee_minimum_amount' => 3.00,
            'service_fee_rule_version' => 1,
        ]);

        $this->auth()->getJson('/api/v1/tenant-tools/ticket-pricing/rule')
            ->assertStatus(200)
            ->assertJsonPath('data.percentage', 10)
            ->assertJsonPath('data.minimum_amount', 3)
            ->assertJsonPath('data.version', 1);
    }

    #[Test]
    public function tenant_user_without_ticket_types_permission_is_forbidden(): void
    {
        $this->auth()->getJson('/api/v1/tenant-tools/ticket-pricing/rule')
            ->assertStatus(403);
    }

    #[Test]
    public function simulate_price_mode_computes_fee_and_buyer_total(): void
    {
        $this->grantPermission('ticket_types', 'read');

        $this->auth()->postJson('/api/v1/tenant-tools/ticket-pricing/simulate', [
            'mode' => 'price',
            'amount' => 2000,
            'quantity' => 3,
            'fee_payer' => 'buyer',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.platform_fee_unit', 3)
            ->assertJsonPath('data.platform_fee_total', 9)
            ->assertJsonPath('data.buyer_pays_unit', 23);
    }

    #[Test]
    public function simulate_target_net_mode_suggests_a_price(): void
    {
        $this->grantPermission('ticket_types', 'read');

        $response = $this->auth()->postJson('/api/v1/tenant-tools/ticket-pricing/simulate', [
            'mode' => 'target_net',
            'amount' => 10000,
            'quantity' => 1,
            'fee_payer' => 'producer',
        ])->assertStatus(200);

        $unitPrice = $response->json('data.unit_price');
        $producerReceives = $response->json('data.producer_receives_unit');

        $this->assertGreaterThanOrEqual(111.10, $unitPrice);
        $this->assertGreaterThanOrEqual(100.0, $producerReceives);
    }
}
