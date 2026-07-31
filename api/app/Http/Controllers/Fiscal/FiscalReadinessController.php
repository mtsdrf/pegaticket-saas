<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use App\Services\Fiscal\FiscalReadinessService;

class FiscalReadinessController extends Controller
{
    public function __construct(
        private FiscalReadinessService $service
    ) {
    }

    public function show()
    {
        return APIResponse::success(
            $this->service->build(tenant()),
            __('messages.fiscal_readiness.shown')
        );
    }
}
