<?php

namespace App\Console\Commands;

use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Services\Logging\ApplicationLogger;
use App\Services\Payment\PagBankPaymentProvider;
use App\Services\Sale\SalePaymentService;
use Illuminate\Console\Command;

/**
 * Reconciliação ativa de cobranças PagBank de pedidos — rede de segurança
 * equivalente a ReconcileMercadoPagoSalePaymentsCommand para o rail
 * comprador -> tenant, para o caso do webhook (PaymentWebhookController::
 * handlePagBank) atrasar/se perder. Reconsulta GET /orders/{id} antes de
 * aplicar qualquer efeito local (reconcileRemotePayment), nunca confia
 * apenas em estado local.
 */
class ReconcilePagBankSalePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-pagbank-sales {--limit=100} {--payment_uuid=}';

    protected $description = 'Reconcilia cobranças PagBank de pedidos ainda pendentes/divergentes/falhas não revisadas.';

    public function __construct(
        private PagBankPaymentProvider $provider,
        private SalePaymentService $orderPaymentService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $paymentUuid = $this->option('payment_uuid');

        $query = Payment::query()
            ->whereNull('deleted_at')
            ->where('provider', 'pagbank')
            ->where('payable_type', Sale::class)
            ->whereNotNull('provider_charge_id')
            ->whereIn('status', ['pending', 'divergent', 'failed'])
            ->orderBy('id');

        if (is_string($paymentUuid) && $paymentUuid !== '') {
            $query->where('uuid', $paymentUuid);
        } else {
            $query->limit($limit);
        }

        $payments = $query->get();

        $reconciled = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            try {
                $remote = $this->provider->getPayment((string) $payment->provider_charge_id);

                $this->orderPaymentService->reconcileRemotePayment(
                    $payment,
                    (string) ($remote['status'] ?? 'pending'),
                    $remote['amount'] ?? (string) $payment->amount,
                );

                $reconciled++;
            } catch (\Throwable $e) {
                $failed++;

                ApplicationLogger::error('Falha na reconciliação ativa de cobrança PagBank', [
                    'payment_uuid' => $payment->uuid,
                    'provider_charge_id' => $payment->provider_charge_id,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Cobranças verificadas: {$payments->count()}.");
        $this->info("Reconciliações concluídas: {$reconciled}.");
        $this->info("Falhas de consulta/reconciliação: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
