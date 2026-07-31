<?php

namespace App\Http\Controllers\Report;

use App\DTOs\Report\CreateReceivableInteractionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReceivableInteractionRequest;
use App\Http\Resources\Report\ReceivableInteractionResource;
use App\Models\Order\Order;
use App\Services\APIResponse;
use App\Services\Report\ReceivableInteractionService;
use Illuminate\Http\Request;

class ReceivableInteractionController extends Controller
{
    public function __construct(
        private ReceivableInteractionService $service
    ) {
    }

    public function index(Request $request, Order $order)
    {
        $items = $this->service->list(
            app('tenant_id'),
            $order,
            $request->get('installment_uuid')
        );

        return APIResponse::success(
            ReceivableInteractionResource::collection($items),
            __('messages.report.receivable_interactions_list')
        );
    }

    public function store(StoreReceivableInteractionRequest $request, Order $order)
    {
        $interaction = $this->service->create(
            app('tenant_id'),
            $order,
            CreateReceivableInteractionDTO::fromArray($request->validated())
        );

        return APIResponse::success(
            new ReceivableInteractionResource($interaction),
            __('messages.report.receivable_interaction_created'),
            201
        );
    }
}
