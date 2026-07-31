<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Subscription\Payment;
use App\Services\Logging\ApplicationLogger;
use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Console\Command;

/**
 * Reconciliação ativa de cobranças Mercado Pago de pedidos.
 *
 * Objetivo: não depender exclusivamente de webhook para atualizar estados
 * locais. Reconsulta a API oficial para cobranças do provider Mercado Pago
 * ainda não terminalizadas localmente.
 *
 * A decisão de reconciliação em si vive em PaymentReconciliationService
 * (2026-07-24) — reaproveitada também pelo endpoint administrativo de
 * reprocessamento manual (`PaymentIssueController::reprocess`), nunca
 * duplicada entre command e endpoint.
 */
class ReconcileMercadoPagoOrderPaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-mercadopago-orders {--limit=100} {--payment_uuid=}';

    protected $description = 'Reconcilia cobranças Mercado Pago de pedidos ainda pendentes/divergentes/falhas não revisadas.';

    public function __construct(
        private PaymentReconciliationService $reconciliationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $paymentUuid = $this->option('payment_uuid');

        $query = Payment::query()
            ->whereNull('deleted_at')
            ->where('provider', 'mercadopago')
            ->where('payable_type', Order::class)
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
                $this->reconciliationService->reconcileOrderPayment($payment);
                $reconciled++;
            } catch (\Throwable $e) {
                $failed++;

                ApplicationLogger::error('Falha na reconciliação ativa de cobrança Mercado Pago', [
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
