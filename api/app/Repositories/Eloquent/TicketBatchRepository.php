<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\TicketBatch;
use App\Repositories\Contracts\TicketBatchRepositoryInterface;

class TicketBatchRepository extends BaseRepository implements TicketBatchRepositoryInterface
{
    public function __construct(TicketBatch $model)
    {
        parent::__construct($model);
    }
}
