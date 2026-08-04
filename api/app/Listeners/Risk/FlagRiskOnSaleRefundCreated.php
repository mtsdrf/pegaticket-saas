<?php

namespace App\Listeners\Risk;

use App\Events\Sale\SaleRefundCreated;
use App\Services\Risk\RiskEngineService;

class FlagRiskOnSaleRefundCreated
{
    public function __construct(
        private RiskEngineService $riskEngineService,
    ) {}

    public function handle(SaleRefundCreated $event): void
    {
        $this->riskEngineService->evaluateRefund($event->saleRefundUuid);
    }
}
