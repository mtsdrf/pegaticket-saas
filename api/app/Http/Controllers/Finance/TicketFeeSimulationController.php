<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SimulateTicketFeeRequest;
use App\Http\Resources\Finance\TicketFeeSimulationResource;
use App\Services\APIResponse;
use App\Services\Finance\PlatformFinanceSettingsService;
use App\Services\Finance\TicketFeeSimulationService;

class TicketFeeSimulationController extends Controller
{
    public function __construct(
        private TicketFeeSimulationService $service,
        private PlatformFinanceSettingsService $platformFinanceSettingsService,
    ) {}

    public function simulate(SimulateTicketFeeRequest $request)
    {
        return APIResponse::success(
            new TicketFeeSimulationResource($this->service->simulate($request->validated())),
            __('messages.ticket_fee_simulation.simulated')
        );
    }

    /**
     * Leitura leve (percentual/mínimo/versão) pra qualquer usuário
     * tenant-scoped que gerencia ingresso — NUNCA expõe os campos
     * sensíveis de split/custódia de `PlatformFinanceSettingsResource`,
     * que exige `payment_admin` (staff da plataforma, não o produtor).
     */
    public function rule()
    {
        $rule = $this->platformFinanceSettingsService->getCurrentServiceFeeRule();

        return APIResponse::success(
            [
                'percentage' => $rule['percentage'],
                'minimum_amount' => round($rule['minimum_cents'] / 100, 2),
                'version' => $rule['version'],
            ],
            __('messages.ticket_fee_simulation.rule_loaded')
        );
    }
}
