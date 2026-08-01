<?php

namespace App\Repositories\Eloquent;

use App\Models\Ticket\TicketCheckin;
use App\Repositories\Contracts\TicketCheckinRepositoryInterface;

class TicketCheckinRepository extends BaseRepository implements TicketCheckinRepositoryInterface
{
    public function __construct(TicketCheckin $model)
    {
        parent::__construct($model);
    }
}
