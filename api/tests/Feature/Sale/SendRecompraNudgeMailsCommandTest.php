<?php

namespace Tests\Feature\Sale;

use App\Mail\RecompraNudgeMail;
use App\Models\Sale\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Automação de recompra (Fase 6, fatia final) — envia "sentimos sua falta"
 * pra compradores cuja última venda paga passou da janela configurada
 * (--days), sem duplicar envio e respeitando isolamento multi-tenant.
 */
class SendRecompraNudgeMailsCommandTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('recompra-user@test.com');
        $this->grantPermission('sales', 'create');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function issuePaidSale(?string $email = null): Sale
    {
        $client = $this->createClient($this->tenant->id);

        if ($email !== null) {
            $client->forceFill(['email' => $email])->save();
        }

        $product = $this->createProduct($this->tenant->id, ['price' => 25]);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        return Sale::where('uuid', $response['uuid'])->firstOrFail();
    }

    #[Test]
    public function sends_a_nudge_for_a_buyer_whose_last_paid_sale_is_older_than_the_configured_window(): void
    {
        Mail::fake();

        $sale = $this->issuePaidSale();
        Sale::whereKey($sale->id)->update(['paid_at' => now()->subDays(90)]);

        $this->artisan('sales:send-recompra-nudges --days=60')->assertExitCode(0);

        Mail::assertSent(RecompraNudgeMail::class, function (RecompraNudgeMail $mail) use ($sale) {
            return $mail->tenant->id === $sale->tenant_id;
        });

        $this->assertNotNull(Sale::whereKey($sale->id)->value('recompra_nudge_sent_at'));
    }

    #[Test]
    public function does_not_nudge_a_buyer_whose_last_purchase_is_within_the_window(): void
    {
        Mail::fake();

        $this->issuePaidSale();

        $this->artisan('sales:send-recompra-nudges --days=60');

        Mail::assertNotSent(RecompraNudgeMail::class);
    }

    #[Test]
    public function does_not_nudge_twice_for_the_same_dormant_sale(): void
    {
        Mail::fake();

        $sale = $this->issuePaidSale();
        Sale::whereKey($sale->id)->update(['paid_at' => now()->subDays(90)]);

        $this->artisan('sales:send-recompra-nudges --days=60');
        $this->artisan('sales:send-recompra-nudges --days=60');

        $this->assertCount(1, Mail::sent(RecompraNudgeMail::class));
    }

    #[Test]
    public function does_not_nudge_a_buyer_who_purchased_again_after_the_dormant_sale(): void
    {
        Mail::fake();

        $sale = $this->issuePaidSale();
        Sale::whereKey($sale->id)->update(['paid_at' => now()->subDays(90)]);

        // Mesmo comprador, compra nova recente — a venda paga MAIS RECENTE
        // passa a ser essa, dentro da janela, então ele não deve ser
        // nudged mesmo a venda antiga estando fora da janela.
        $client = $sale->finalCustomer;
        $product = $this->createProduct($this->tenant->id, ['price' => 15]);
        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $this->artisan('sales:send-recompra-nudges --days=60');

        Mail::assertNotSent(RecompraNudgeMail::class);
    }

    #[Test]
    public function only_considers_sales_within_the_acting_tenant(): void
    {
        Mail::fake();

        $sale = $this->issuePaidSale();
        Sale::whereKey($sale->id)->update(['paid_at' => now()->subDays(90)]);

        $this->artisan('sales:send-recompra-nudges --days=60');

        Mail::assertSent(RecompraNudgeMail::class, function (RecompraNudgeMail $mail) use ($sale) {
            return $mail->finalCustomer->id === $sale->final_customer_id
                && $mail->tenant->id === $sale->tenant_id;
        });
    }
}
