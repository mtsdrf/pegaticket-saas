<?php

namespace App\Repositories\Eloquent;

use App\Models\Sale\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    public function __construct(Sale $model)
    {
        parent::__construct($model);
    }
}
