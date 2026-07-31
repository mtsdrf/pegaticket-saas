<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use App\Services\Fiscal\FiscalReadinessCheckService;

class FiscalReadinessController extends Controller
{
    public function __construct(
        private FiscalReadinessCheckService $service
    ) {
    }

    public function show()
    {
        return APIResponse::success(
            $this->service->forTenant(tenant()),
            __('messages.fiscal_readiness.shown')
        );
    }
}
