<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePaid;
use App\Services\Finance\ReceivableGenerationService;

class CreateReceivableOnSalePaid
{
    public function __construct(
        private ReceivableGenerationService $receivableGenerationService,
    ) {}

    public function handle(SalePaid $event): void
    {
        $this->receivableGenerationService->generateForSaleUuid($event->saleUuid);
    }
}
