<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePaid;
use App\Services\Affiliate\AffiliateCommissionService;

class CreateAffiliateCommissionOnSalePaid
{
    public function __construct(
        private AffiliateCommissionService $affiliateCommissionService,
    ) {}

    public function handle(SalePaid $event): void
    {
        $this->affiliateCommissionService->generateForSaleUuid($event->saleUuid);
    }
}
