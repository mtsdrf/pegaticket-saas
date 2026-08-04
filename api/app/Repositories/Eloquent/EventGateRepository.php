<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\EventGate;
use App\Repositories\Contracts\EventGateRepositoryInterface;

class EventGateRepository extends BaseRepository implements EventGateRepositoryInterface
{
    public function __construct(EventGate $model)
    {
        parent::__construct($model);
    }
}
