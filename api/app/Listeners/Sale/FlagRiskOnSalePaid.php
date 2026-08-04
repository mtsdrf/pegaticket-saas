<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePaid;
use App\Services\Risk\RiskEngineService;

class FlagRiskOnSalePaid
{
    public function __construct(
        private RiskEngineService $riskEngineService,
    ) {}

    public function handle(SalePaid $event): void
    {
        $this->riskEngineService->evaluateSalePaid($event->saleUuid);
    }
}
