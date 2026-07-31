<?php

namespace App\Console\Commands;

use App\Models\Payment\PaymentIdempotencyKey;
use App\Repositories\Contracts\IdempotencyRepositoryInterface;
use App\Services\Logging\ApplicationLogger;
use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Console\Command;

/**
 * Fecha o loop do timeout ambíguo de verdade (achado de risco ALTO,
 * 2026-07-24): quando `payment_idempotency_keys` tem uma linha `pending`
 * com o lock expirado, este comando reconsulta o Mercado Pago por
 * `external_reference` para decidir com segurança o que realmente
 * aconteceu antes de qualquer nova tentativa ser permitida.
 *
 * A decisão de reconciliação em si vive em PaymentReconciliationService
 * (2026-07-24) — reaproveitada também pelo endpoint administrativo de
 * reprocessamento manual (`PaymentIssueController::reprocess`), nunca
 * duplicada entre command e endpoint.
 */
class ReconcilePaymentIdempotencyCommand extends Command
{
    protected $signature = 'payments:reconcile-idempotency {--limit=100}';

    protected $description = 'Reconcilia tentativas de idempotência pending com lock expirado contra o Mercado Pago.';

    public function __construct(
        private IdempotencyRepositoryInterface $idempotencyRepository,
        private PaymentReconciliationService $reconciliationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $records = $this->idempotencyRepository->findExpiredPending($limit);

        $resolved = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                $this->reconciliationService->reconcileIdempotencyRecord($record);
                $resolved++;
            } catch (\Throwable $e) {
                $failed++;

                $this->logFailure($record, $e);
            }
        }

        $this->info("Tentativas verificadas: {$records->count()}.");
        $this->info("Reconciliações concluídas: {$resolved}.");
        $this->info("Falhas de consulta/reconciliação: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function logFailure(PaymentIdempotencyKey $record, \Throwable $e): void
    {
        ApplicationLogger::error('Falha na reconciliação de idempotência de pagamento', [
            'idempotency_key_id' => $record->id,
            'operation' => $record->operation,
            'exception_class' => get_class($e),
            'exception_message' => $e->getMessage(),
        ]);
    }
}
