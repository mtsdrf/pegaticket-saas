<?php

namespace Tests\Feature\Console;

use App\Enums\Payment\PaymentStatus;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * `payments:reconcile-pagbank-sales` (roadmap R5, gap 2.9 — métrica
 * `pagbank_reconciliation_divergences`, skill pagbank-integration.md §54).
 */
class ReconcilePagBankSalePaymentsCommandTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-pagbank-token');

        $this->setUpTenantScopedUser('reconcile-pagbank-user@test.com');
        File::delete(storage_path('logs/pagbank-transactions.log'));
    }

    #[Test]
    public function logs_a_reconciliation_divergence_metric_when_the_remote_amount_does_not_match(): void
    {
        $orderId = 'ORDE_DIVERGENT_1';

        Http::fake([
            "sandbox.api.pagseguro.com/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'charges' => [[
                    'id' => 'CHAR_DIVERGENT_1',
                    'status' => 'PAID',
                    'amount' => ['value' => 9999, 'currency' => 'BRL'],
                ]],
            ], 200),
        ]);

        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 80]);

        $sale = Sale::create([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'total_amount' => '80.00',
            'is_paid' => false,
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        $payment = Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'provider' => 'pagbank',
            'provider_charge_id' => $orderId,
            'method' => 'pix',
            'amount' => '80.00',
            'status' => 'pending',
        ]);

        Artisan::call('payments:reconcile-pagbank-sales');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Divergent, $payment->status);

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_reconciliation_divergences"', $contents);
    }
}
