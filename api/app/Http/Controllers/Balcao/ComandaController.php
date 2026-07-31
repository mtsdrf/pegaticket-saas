<?php

namespace App\Http\Controllers\Balcao;

use App\DTOs\Balcao\AddComandaItemDTO;
use App\DTOs\Balcao\CloseComandaDTO;
use App\DTOs\Balcao\OpenComandaDTO;
use App\DTOs\Balcao\UpdatePrepStatusDTO;
use App\Exceptions\Balcao\ComandaException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\Pdv\PaymentAmountMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Balcao\AddComandaItemRequest;
use App\Http\Requests\Balcao\CloseComandaRequest;
use App\Http\Requests\Balcao\OpenComandaRequest;
use App\Http\Requests\Balcao\UpdatePrepStatusRequest;
use App\Http\Resources\Balcao\ComandaItemResource;
use App\Http\Resources\Balcao\ComandaResource;
use App\Http\Resources\Order\OrderResource;
use App\Models\Balcao\ComandaItem;
use App\Services\APIResponse;
use App\Services\Balcao\ComandaItemService;
use App\Services\Balcao\ComandaService;

class ComandaController extends Controller
{
    public function __construct(
        private ComandaService $service,
        private ComandaItemService $itemService,
    ) {
    }

    public function index()
    {
        $comandas = $this->service->listOpen(app('tenant_id'));

        return APIResponse::success(
            ComandaResource::collection($comandas),
            __('messages.comanda.list')
        );
    }

    public function store(OpenComandaRequest $request)
    {
        $dto = OpenComandaDTO::fromArray($request->validated());

        $comanda = $this->service->open(app('tenant_id'), $dto);

        return APIResponse::success(
            new ComandaResource($comanda),
            __('messages.comanda.opened'),
            201
        );
    }

    public function addItem(AddComandaItemRequest $request, string $uuid)
    {
        $dto = AddComandaItemDTO::fromArray($request->validated());

        try {
            $item = $this->service->addItem(app('tenant_id'), $uuid, $dto);
        } catch (ComandaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'COMANDA_ERROR');
        }

        return APIResponse::success(
            new ComandaItemResource($item),
            __('messages.comanda.item_added'),
            201
        );
    }

    public function updatePrepStatus(UpdatePrepStatusRequest $request, string $uuid, string $itemUuid)
    {
        $dto = UpdatePrepStatusDTO::fromArray($request->validated());
        $tenantId = app('tenant_id');

        try {
            // 'sent_to_station' é a única transição que baixa estoque — vai
            // pelo método dedicado; as demais são transições puras da máquina
            // de estados.
            $item = $dto->prepStatus === ComandaItem::STATUS_SENT_TO_STATION
                ? $this->itemService->sendToStation($tenantId, $uuid, $itemUuid)
                : $this->itemService->updatePrepStatus($tenantId, $uuid, $itemUuid, $dto);
        } catch (ComandaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'COMANDA_ERROR');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        }

        return APIResponse::success(
            new ComandaItemResource($item),
            __('messages.comanda.item_prep_status_updated')
        );
    }

    public function close(CloseComandaRequest $request, string $uuid)
    {
        $dto = CloseComandaDTO::fromArray($request->validated());

        try {
            $order = $this->service->close(app('tenant_id'), $uuid, $dto);
        } catch (ComandaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'COMANDA_ERROR');
        } catch (PaymentAmountMismatchException $e) {
            return APIResponse::error($e->getMessage(), 422, 'PAYMENT_AMOUNT_MISMATCH');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            new OrderResource($order),
            __('messages.comanda.closed'),
            201
        );
    }
}
