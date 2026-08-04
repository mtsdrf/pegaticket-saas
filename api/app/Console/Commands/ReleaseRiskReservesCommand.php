<?php

namespace App\Console\Commands;

use App\Services\Finance\RiskReserveReleaseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReleaseRiskReservesCommand extends Command
{
    protected $signature = 'finance:release-risk-reserves {--date=} {--tenant_id=} {--receivable_uuid=}';

    protected $description = 'Libera reservas de risco retidas sobre recebiveis cujo prazo de retencao ja venceu.';

    public function __construct(
        private RiskReserveReleaseService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $tenantIdOption = $this->option('tenant_id');
        $receivableUuid = $this->option('receivable_uuid');

        $cutoffAt = is_string($dateOption) && $dateOption !== ''
            ? Carbon::parse($dateOption)->endOfDay()
            : now();

        $tenantId = is_numeric($tenantIdOption) ? (int) $tenantIdOption : null;

        $result = $this->service->releaseDue(
            cutoffAt: $cutoffAt,
            tenantId: $tenantId,
            receivableUuid: is_string($receivableUuid) && $receivableUuid !== '' ? $receivableUuid : null,
        );

        $this->info("Reservas verificadas: {$result['reserves_seen']}.");
        $this->info("Reservas liberadas: {$result['released']}.");
        $this->info("Reservas ignoradas: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
