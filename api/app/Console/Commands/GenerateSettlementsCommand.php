<?php

namespace App\Console\Commands;

use App\Services\Finance\SettlementGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateSettlementsCommand extends Command
{
    protected $signature = 'finance:generate-settlements {--date=} {--tenant_id=}';

    protected $description = 'Agrupa recebíveis elegíveis em lotes locais de repasse (settlements).';

    public function __construct(
        private SettlementGenerationService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $tenantIdOption = $this->option('tenant_id');

        $cutoffAt = is_string($dateOption) && $dateOption !== ''
            ? Carbon::parse($dateOption)->endOfDay()
            : now();

        $tenantId = is_numeric($tenantIdOption) ? (int) $tenantIdOption : null;

        $result = $this->service->generateAvailable($cutoffAt, $tenantId);

        $this->info("Grupos elegíveis verificados: {$result['groups_seen']}.");
        $this->info("Settlements criados: {$result['settlements_created']}.");
        $this->info("Recebíveis vinculados: {$result['receivables_linked']}.");

        return self::SUCCESS;
    }
}
