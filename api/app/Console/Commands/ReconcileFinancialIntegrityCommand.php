<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialIntegrityReconciliationService;
use Illuminate\Console\Command;

class ReconcileFinancialIntegrityCommand extends Command
{
    protected $signature = 'finance:reconcile-financial-integrity {--tenant_id=}';

    protected $description = 'Varre divergências internas entre receivables, settlements, adjustments e ledger.';

    public function __construct(
        private FinancialIntegrityReconciliationService $service,
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

        return self::SUCCESS;
    }
}
