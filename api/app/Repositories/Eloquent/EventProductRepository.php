<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\EventProduct;
use App\Repositories\Contracts\EventProductRepositoryInterface;

class EventProductRepository extends BaseRepository implements EventProductRepositoryInterface
{
    public function __construct(EventProduct $model)
    {
        parent::__construct($model);
    }
}
