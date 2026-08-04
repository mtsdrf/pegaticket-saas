<?php

namespace App\Console\Commands;

use App\Services\Finance\SettlementReleaseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReleaseSettlementsCommand extends Command
{
    protected $signature = 'finance:release-settlements {--date=} {--tenant_id=} {--settlement_uuid=}';

    protected $description = 'Libera settlements vencidos, incluindo custódia PagBank quando houver split. ';

    public function __construct(
        private SettlementReleaseService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $tenantIdOption = $this->option('tenant_id');
        $settlementUuid = $this->option('settlement_uuid');

        $cutoffAt = is_string($dateOption) && $dateOption !== ''
            ? Carbon::parse($dateOption)->endOfDay()
            : now();

        $tenantId = is_numeric($tenantIdOption) ? (int) $tenantIdOption : null;

        $result = $this->service->releaseDue(
            cutoffAt: $cutoffAt,
            tenantId: $tenantId,
            settlementUuid: is_string($settlementUuid) && $settlementUuid !== '' ? $settlementUuid : null,
        );

        $this->info("Settlements verificados: {$result['settlements_seen']}.");
        $this->info("Settlements liberados: {$result['released']}.");
        $this->info("Settlements com liberação solicitada: {$result['release_requested']}.");
        $this->info("Settlements ignorados: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
