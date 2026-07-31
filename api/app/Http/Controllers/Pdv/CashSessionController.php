<?php

namespace App\Http\Controllers\Pdv;

use App\DTOs\Pdv\CloseCashSessionDTO;
use App\DTOs\Pdv\OpenCashSessionDTO;
use App\DTOs\Pdv\RegisterCashMovementDTO;
use App\Exceptions\Pdv\CashSessionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pdv\CloseCashSessionRequest;
use App\Http\Requests\Pdv\OpenCashSessionRequest;
use App\Http\Requests\Pdv\RegisterCashMovementRequest;
use App\Http\Resources\Pdv\CashMovementResource;
use App\Http\Resources\Pdv\CashSessionResource;
use App\Services\APIResponse;
use App\Services\Pdv\CashSessionService;

class CashSessionController extends Controller
{
    public function __construct(
        private CashSessionService $service
    ) {
    }

    public function index()
    {
        $sessions = $this->service->list(app('tenant_id'));

        return APIResponse::success(
            CashSessionResource::collection($sessions),
            __('messages.pdv.session_list')
        );
    }

    public function current()
    {
        $session = $this->service->current(app('tenant_id'));

        return APIResponse::success(
            $session ? new CashSessionResource($session) : null,
            __('messages.pdv.session_current')
        );
    }

    public function store(OpenCashSessionRequest $request)
    {
        $dto = OpenCashSessionDTO::fromArray($request->validated());

        try {
            $session = $this->service->open(app('tenant_id'), $dto);
        } catch (CashSessionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'CASH_SESSION_ERROR');
        }

        return APIResponse::success(
            new CashSessionResource($session),
            __('messages.pdv.session_opened'),
            201
        );
    }

    public function movements(RegisterCashMovementRequest $request, string $uuid)
    {
        $dto = RegisterCashMovementDTO::fromArray($request->validated());

        try {
            $movement = $this->service->registerMovement(app('tenant_id'), $uuid, $dto);
        } catch (CashSessionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'CASH_SESSION_ERROR');
        }

        return APIResponse::success(
            new CashMovementResource($movement),
            __('messages.pdv.movement_registered'),
            201
        );
    }

    public function close(CloseCashSessionRequest $request, string $uuid)
    {
        $dto = CloseCashSessionDTO::fromArray($request->validated());

        try {
            $session = $this->service->close(app('tenant_id'), $uuid, $dto);
        } catch (CashSessionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'CASH_SESSION_ERROR');
        }

        return APIResponse::success(
            new CashSessionResource($session),
            __('messages.pdv.session_closed')
        );
    }
}
