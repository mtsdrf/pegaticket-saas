<?php

namespace Tests\Feature\Fiscal;

use App\Contracts\Fiscal\FiscalProviderInterface;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Subscription\Invoice;
use App\Models\Tenant\Tenant;
use App\Services\Fiscal\ManualFiscalProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

class FiscalDocumentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSubscriptionFixtures;

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Fiscal Tenant',
            'slug' => 'fiscal-' . Str::random(8),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function manual_provider_is_bound_by_default(): void
    {
        $this->assertInstanceOf(
            ManualFiscalProvider::class,
            app(FiscalProviderInterface::class)
        );
    }

    #[Test]
    public function issue_always_results_in_provider_submitted_status(): void
    {
        $tenant = $this->makeTenant();

        $document = FiscalDocument::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'documentable_type' => Invoice::class,
            'documentable_id' => 1,
            'document_type' => 'nfe',
            'status' => 'pending',
            'provider' => 'none',
        ]);

        $result = app(FiscalProviderInterface::class)->issue($document);

        $this->assertSame('provider_submitted', $result['status']);
        $this->assertSame('manual', $result['provider']);
        $this->assertSame('MANUAL-' . $document->uuid, $result['provider_document_id']);

        $fresh = $document->fresh();
        $this->assertSame('provider_submitted', $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame('manual', $fresh->provider);
        $this->assertSame('MANUAL-' . $document->uuid, $fresh->provider_document_id);
        $this->assertNull($fresh->authorized_at);
        $this->assertNull($fresh->access_key);
    }

    #[Test]
    public function manual_provider_status_query_returns_pending_snapshot(): void
    {
        $status = app(FiscalProviderInterface::class)->getStatus('MANUAL-123');

        $this->assertSame('MANUAL-123', $status['provider_document_id']);
        $this->assertSame('manual', $status['provider']);
        $this->assertSame('pending', $status['status']);
        $this->assertNotEmpty($status['checked_at']);
    }

    #[Test]
    public function invoice_fiscal_document_id_accepts_null(): void
    {
        $subscription = $this->createSubscription();

        $invoice = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'competence_period' => '2026-08',
            'due_date' => now()->addDays(10),
            'amount_gross' => 99.90,
            'discount_amount' => 0,
            'amount_net' => 99.90,
            'status' => 'open',
            'fiscal_document_id' => null,
        ]);

        $this->assertNull($invoice->fresh()->fiscal_document_id);
    }

    #[Test]
    public function invoice_accepts_valid_fiscal_document_link(): void
    {
        $subscription = $this->createSubscription();

        $document = FiscalDocument::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $subscription->tenant_id,
            'documentable_type' => Invoice::class,
            'documentable_id' => 1,
            'document_type' => 'nfse',
            'status' => 'pending',
            'provider' => 'manual',
        ]);

        $invoice = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'competence_period' => '2026-08',
            'due_date' => now()->addDays(10),
            'amount_gross' => 99.90,
            'discount_amount' => 0,
            'amount_net' => 99.90,
            'status' => 'open',
            'fiscal_document_id' => $document->id,
        ]);

        $this->assertSame($document->id, $invoice->fresh()->fiscal_document_id);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'fiscal_document_id' => $document->id,
        ]);
    }

    #[Test]
    public function cancel_sets_canceled_status_and_reason(): void
    {
        $tenant = $this->makeTenant();

        $document = FiscalDocument::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'documentable_type' => Invoice::class,
            'documentable_id' => 1,
            'document_type' => 'nfce',
            'status' => 'pending',
            'provider' => 'manual',
        ]);

        app(FiscalProviderInterface::class)->cancel($document, 'Emissao indevida');

        $fresh = $document->fresh();
        $this->assertSame('canceled', $fresh->status);
        $this->assertSame('Emissao indevida', $fresh->rejection_reason);
        $this->assertNotNull($fresh->canceled_at);
    }
}
