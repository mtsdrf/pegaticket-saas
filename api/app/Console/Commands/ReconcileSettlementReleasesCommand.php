<?php

namespace App\Console\Commands;

use App\Services\Finance\SettlementReleaseService;
use Illuminate\Console\Command;

class ReconcileSettlementReleasesCommand extends Command
{
    protected $signature = 'finance:reconcile-settlement-releases {--tenant_id=} {--settlement_uuid=}';

    protected $description = 'Reconcilia settlements em release_requested consultando novamente o split no PagBank.';

    public function __construct(
        private SettlementReleaseService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantIdOption = $this->option('tenant_id');
        $settlementUuid = $this->option('settlement_uuid');

        $tenantId = is_numeric($tenantIdOption) ? (int) $tenantIdOption : null;

        $result = $this->service->reconcileRequested(
            tenantId: $tenantId,
            settlementUuid: is_string($settlementUuid) && $settlementUuid !== '' ? $settlementUuid : null,
        );

        $this->info("Settlements verificados: {$result['settlements_seen']}.");
        $this->info("Settlements consolidados como liberados: {$result['released']}.");
        $this->info("Settlements ainda pendentes: {$result['still_pending']}.");
        $this->info("Settlements ignorados: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
