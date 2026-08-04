<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleRefundCreated;
use App\Services\Finance\SaleRefundFinancialAdjustmentService;

class RegisterFinancialAdjustmentOnSaleRefund
{
    public function __construct(
        private SaleRefundFinancialAdjustmentService $service,
    ) {}

    public function handle(SaleRefundCreated $event): void
    {
        $this->service->handleRefund(
            saleUuid: $event->saleUuid,
            saleRefundUuid: $event->saleRefundUuid,
        );
    }
}
