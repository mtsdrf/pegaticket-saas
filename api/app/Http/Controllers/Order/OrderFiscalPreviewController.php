<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Services\APIResponse;
use App\Services\Fiscal\OrderFiscalPreviewService;
use App\Services\Order\OrderService;

class OrderFiscalPreviewController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderFiscalPreviewService $previewService,
    ) {
    }

    public function show(Order $order)
    {
        $order = $this->orderService->find($order);
        $order->loadMissing('latestFiscalDocument');

        return APIResponse::success(
            $this->previewService->preview($order),
            __('messages.order.fiscal_preview')
        );
    }
}
