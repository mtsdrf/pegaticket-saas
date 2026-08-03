<?php

namespace App\Http\Controllers\CashSession;

use App\DTOs\CashSession\CloseCashSessionDTO;
use App\DTOs\CashSession\OpenCashSessionDTO;
use App\Exceptions\InvalidCashSessionStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashSession\CloseCashSessionRequest;
use App\Http\Requests\CashSession\OpenCashSessionRequest;
use App\Http\Resources\CashSession\CashSessionResource;
use App\Services\APIResponse;
use App\Services\CashSession\CashSessionService;

/**
 * Caixa (roadmap Fase 2 — "caixa e estações de venda"). Fino, só orquestra
 * — regra de negócio (um caixa por vez, cálculo do valor esperado) vive em
 * CashSessionService.
 */
class CashSessionController extends Controller
{
    public function __construct(
        private CashSessionService $service,
    ) {}

    public function index()
    {
        return APIResponse::success(
            CashSessionResource::collection($this->service->paginate()),
            __('messages.cash_session.list')
        );
    }

    public function current()
    {
        $session = $this->service->current();

        return APIResponse::success(
            $session ? new CashSessionResource($session) : null,
            __('messages.cash_session.current')
        );
    }

    public function open(OpenCashSessionRequest $request)
    {
        $dto = OpenCashSessionDTO::fromArray($request->validated());

        try {
            $session = $this->service->open($dto);
        } catch (InvalidCashSessionStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_CASH_SESSION_STATE');
        }

        return APIResponse::success(
            new CashSessionResource($session),
            __('messages.cash_session.opened'),
            201
        );
    }

    public function close(CloseCashSessionRequest $request)
    {
        $dto = CloseCashSessionDTO::fromArray($request->validated());

        try {
            $session = $this->service->close($dto);
        } catch (InvalidCashSessionStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_CASH_SESSION_STATE');
        }

        return APIResponse::success(
            new CashSessionResource($session),
            __('messages.cash_session.closed')
        );
    }
}
