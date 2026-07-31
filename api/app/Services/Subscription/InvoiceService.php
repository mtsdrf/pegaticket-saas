<?php

namespace App\Services\Subscription;

use App\Models\Subscription\Invoice;
use App\Models\Subscription\Subscription;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Geração de faturas de assinatura (roadmap 1B). O desconto vem congelado
 * do plan_price (discount_percent), aplicado sobre amount_gross. Cálculo em
 * centavos internamente (mesmo cuidado do CouponService), gravado em
 * decimal(10,2).
 */
class InvoiceService
{
    public function __construct(
        private InvoiceRepositoryInterface $repository
    ) {
    }

    public function generateForCycle(Subscription $subscription): Invoice
    {
        $planPrice = $subscription->planPrice()->withTrashed()->firstOrFail();

        $breakdown = Money::applyDiscount($planPrice->amount, $planPrice->discount_percent);

        $periodStart = $subscription->current_period_start ?? now();

        return DB::transaction(function () use ($subscription, $breakdown, $periodStart) {
            return $this->repository->create([
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'competence_period' => $periodStart->format('Y-m'),
                'due_date' => now()->addDays(7)->toDateString(),
                'amount_gross' => $breakdown['gross'],
                'discount_amount' => $breakdown['discount'],
                'amount_net' => $breakdown['net'],
                'status' => 'open',
            ]);
        });
    }

    /**
     * Fatura do ciclo corrente da assinatura (por `competence_period`
     * derivado de `current_period_start`) — cria se ainda não existir.
     * Usada pelo webhook de Preapproval (cobrança dirigida pelo MP, não
     * pelo cron `subscriptions:generate-invoices`).
     */
    public function findOrCreateForCurrentCycle(Subscription $subscription): Invoice
    {
        $competencePeriod = ($subscription->current_period_start ?? now())->format('Y-m');

        $existing = Invoice::query()
            ->whereNull('deleted_at')
            ->where('subscription_id', $subscription->id)
            ->where('competence_period', $competencePeriod)
            ->first();

        return $existing ?? $this->generateForCycle($subscription);
    }

    /**
     * Marca a fatura como paga a partir da confirmação do PSP (webhook).
     * Idempotente: uma fatura já paga não é reprocessada — protege contra
     * reentrega duplicada do mesmo evento de pagamento. Reconciliação de
     * valor ANTES de confirmar (mesma regra de `OrderPaymentService::
     * reconcileWebhookPayment`): o valor reportado pelo Mercado Pago
     * precisa bater com `amount_net` em centavos — divergência marca
     * `divergent` em vez de `paid` e não confirma o ciclo sozinho, exige
     * conferência humana.
     *
     * @return bool true se a fatura foi confirmada como paga (ou já estava).
     */
    public function markPaidFromWebhook(Invoice $invoice, string|int|float $amountPaid): bool
    {
        if ($invoice->status === 'paid') {
            return true;
        }

        if (! Money::equals((string) $invoice->amount_net, $amountPaid)) {
            $invoice->fill(['status' => 'divergent']);
            $invoice->save();

            return false;
        }

        $invoice->fill(['status' => 'paid']);
        $invoice->save();

        return true;
    }

    /**
     * Histórico paginado de faturas do tenant (todas as subscriptions dele
     * ao longo do tempo). Usado pela tela "Empresa" do proprietário.
     */
    public function listForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForTenant($tenantId, $perPage);
    }
}
