<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialIntegrityReconciliationService;
use App\Services\Payment\PagBankTransactionLogger;
use Illuminate\Console\Command;

class ReconcileFinancialIntegrityCommand extends Command
{
    protected $signature = 'finance:reconcile-financial-integrity {--tenant_id=}';

    protected $description = 'Varre divergências internas entre receivables, settlements, adjustments e ledger.';

    public function __construct(
        private FinancialIntegrityReconciliationService $service,
        private PagBankTransactionLogger $pagBankTransactionLogger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantIdOption = $this->option('tenant_id');
        $tenantId = is_numeric($tenantIdOption) ? (int) $tenantIdOption : null;

        $report = $this->service->buildReport($tenantId);

        $this->info("Recebíveis sem settlement elegível: {$report['summary']['receivables_without_settlement']}.");
        $this->info("Settlements com net divergente: {$report['summary']['settlement_net_mismatches']}.");
        $this->info("Settlements liberados sem ledger: {$report['summary']['released_settlements_missing_ledger']}.");
        $this->info("Ajustes abertos: {$report['summary']['open_adjustments']}.");

        $totalIssues = array_sum($report['summary']);

        // Roadmap R5, skill pagbank-integration.md §54: este é hoje o único
        // comando de reconciliação financeira do projeto que varre
        // divergências internas (receivables/settlements/ledger) que também
        // cobrem o rail PagBank (settlement/receivable de venda paga via
        // PagBank) — não existe um comando de reconciliação "PagBank-only"
        // separado deste para esse tipo de divergência (o outro,
        // ReconcilePagBankSalePaymentsCommand, cobre só status remoto de
        // pagamento). Por isso a métrica nomeada pela skill é logada aqui.
        if ($totalIssues > 0) {
            $this->pagBankTransactionLogger->metric('pagbank_reconciliation_divergences', [
                'tenant_id' => $tenantId,
                'total_issues' => $totalIssues,
                'summary' => $report['summary'],
            ]);
        }

        return self::SUCCESS;
    }
}
