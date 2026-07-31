<?php

namespace App\Repositories\Eloquent;

use App\Models\Stock\StockBalance;
use App\Repositories\Contracts\StockBalanceRepositoryInterface;

class StockBalanceRepository extends BaseRepository implements StockBalanceRepositoryInterface
{
    public function __construct(StockBalance $model)
    {
        parent::__construct($model);
    }
}
