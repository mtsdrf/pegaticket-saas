<?php

namespace Tests\Unit\Services\Payment;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use App\Repositories\Eloquent\TenantPagBankConnectionRepository;
use App\Services\Payment\TenantReceivingEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tabela de verdade completa dos 13 status de TenantPagBankConnection
 * (roadmap R2.3, seção 9.5.3) contra os 4 métodos do eligibility service —
 * única fonte de verdade de elegibilidade financeira do tenant.
 */
class TenantReceivingEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantReceivingEligibilityService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TenantReceivingEligibilityService(
            new TenantPagBankConnectionRepository(new TenantPagBankConnection)
        );

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Eligibility Tenant',
            'slug' => 'eligibility-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function createConnection(string $status): void
    {
        TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => $status,
            'environment' => 'sandbox',
        ]);
    }

    public static function allStatusesProvider(): array
    {
        return [
            'not_configured' => [TenantPagBankConnection::STATUS_NOT_CONFIGURED, false, false, true, false],
            'pending_connection' => [TenantPagBankConnection::STATUS_PENDING_CONNECTION, false, false, false, false],
            'pending_kyc' => [TenantPagBankConnection::STATUS_PENDING_KYC, false, false, false, false],
            'under_review' => [TenantPagBankConnection::STATUS_UNDER_REVIEW, false, false, false, false],
            'enabled' => [TenantPagBankConnection::STATUS_ENABLED, true, true, false, false],
            'restricted' => [TenantPagBankConnection::STATUS_RESTRICTED, false, false, false, true],
            'disabled' => [TenantPagBankConnection::STATUS_DISABLED, false, false, true, false],
            'started' => [TenantPagBankConnection::STATUS_STARTED, false, false, false, false],
            'pending_submission' => [TenantPagBankConnection::STATUS_PENDING_SUBMISSION, false, false, false, false],
            'submitted' => [TenantPagBankConnection::STATUS_SUBMITTED, false, false, false, false],
            'verified' => [TenantPagBankConnection::STATUS_VERIFIED, true, true, false, false],
            'rejected' => [TenantPagBankConnection::STATUS_REJECTED, false, false, false, true],
            'error' => [TenantPagBankConnection::STATUS_ERROR, false, false, false, false],
        ];
    }

    #[Test]
    #[DataProvider('allStatusesProvider')]
    public function evaluates_every_status_against_all_four_methods(
        string $status,
        bool $expectedCanReceive,
        bool $expectedCanPublish,
        bool $expectedNeedsSetup,
        bool $expectedHasRestriction
    ): void {
        $this->createConnection($status);

        $this->assertSame($expectedCanReceive, $this->service->canReceivePayments($this->tenant->id), "canReceivePayments for {$status}");
        $this->assertSame($expectedCanPublish, $this->service->canPublishPaidEvents($this->tenant->id), "canPublishPaidEvents for {$status}");
        $this->assertSame($expectedNeedsSetup, $this->service->needsReceivingSetup($this->tenant->id), "needsReceivingSetup for {$status}");
        $this->assertSame($expectedHasRestriction, $this->service->hasFinancialRestriction($this->tenant->id), "hasFinancialRestriction for {$status}");
    }

    #[Test]
    public function tenant_with_no_connection_at_all_needs_setup_and_has_no_other_flag(): void
    {
        $this->assertFalse($this->service->canReceivePayments($this->tenant->id));
        $this->assertFalse($this->service->canPublishPaidEvents($this->tenant->id));
        $this->assertTrue($this->service->needsReceivingSetup($this->tenant->id));
        $this->assertFalse($this->service->hasFinancialRestriction($this->tenant->id));
    }

    #[Test]
    public function can_receive_payments_and_can_publish_paid_events_are_independent_methods_with_equal_result_today(): void
    {
        $this->createConnection(TenantPagBankConnection::STATUS_ENABLED);

        $this->assertSame(
            $this->service->canReceivePayments($this->tenant->id),
            $this->service->canPublishPaidEvents($this->tenant->id)
        );
    }
}
