<?php

namespace App\Repositories\Eloquent;

use App\Models\Sale\Sale;
use App\Models\Sale\SaleRefund;
use App\Repositories\Contracts\SaleRefundRepositoryInterface;
use Illuminate\Support\Collection;

class SaleRefundRepository extends BaseRepository implements SaleRefundRepositoryInterface
{
    public function __construct(SaleRefund $model)
    {
        parent::__construct($model);
    }

    public function sumAmountForOrder(Sale $order): float
    {
        return (float) SaleRefund::where('order_id', $order->id)
            ->whereNull('deleted_at')
            ->sum('amount');
    }

    public function listForOrder(Sale $order): Collection
    {
        return SaleRefund::where('order_id', $order->id)
            ->whereNull('deleted_at')
            ->with('tickets')
            ->orderByDesc('id')
            ->get();
    }
}
