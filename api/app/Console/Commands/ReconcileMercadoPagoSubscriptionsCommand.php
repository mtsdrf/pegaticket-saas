<?php

namespace App\Console\Commands;

use App\Models\Subscription\Subscription;
use App\Services\Logging\ApplicationLogger;
use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Console\Command;

/**
 * Reconciliação ativa das assinaturas/preapprovals no Mercado Pago.
 *
 * Não substitui os webhooks de cobrança do ciclo; atua como rede de
 * segurança para sincronizar o status estrutural do vínculo recorrente
 * (`authorized`, `cancelled`, etc.) com o estado local.
 *
 * A decisão de reconciliação em si vive em PaymentReconciliationService
 * (2026-07-24), mesmo núcleo compartilhado usado pelas outras
 * reconciliações ativas de pagamento/idempotência.
 */
class ReconcileMercadoPagoSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:reconcile-mercadopago {--limit=100} {--subscription_uuid=}';

    protected $description = 'Reconcilia assinaturas Mercado Pago a partir do status atual do preapproval remoto.';

    public function __construct(
        private PaymentReconciliationService $reconciliationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $subscriptionUuid = $this->option('subscription_uuid');

        $query = Subscription::query()
            ->whereNull('deleted_at')
            ->whereNotNull('preapproval_id')
            ->whereIn('status', ['pending', 'trialing', 'active', 'past_due', 'suspended', 'cancel_scheduled'])
            ->orderBy('id');

        if (is_string($subscriptionUuid) && $subscriptionUuid !== '') {
            $query->where('uuid', $subscriptionUuid);
        } else {
            $query->limit($limit);
        }

        $subscriptions = $query->get();

        $reconciled = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->reconciliationService->reconcileSubscriptionPreapproval($subscription);
                $reconciled++;
            } catch (\Throwable $e) {
                $failed++;

                ApplicationLogger::error('Falha na reconciliação ativa de assinatura Mercado Pago', [
                    'subscription_uuid' => $subscription->uuid,
                    'preapproval_id' => $subscription->preapproval_id,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Assinaturas verificadas: {$subscriptions->count()}.");
        $this->info("Reconciliações concluídas: {$reconciled}.");
        $this->info("Falhas de consulta/reconciliação: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
