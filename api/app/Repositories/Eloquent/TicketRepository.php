<?php

namespace App\Repositories\Eloquent;

use App\Models\Ticket\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;

class TicketRepository extends BaseRepository implements TicketRepositoryInterface
{
    public function __construct(Ticket $model)
    {
        parent::__construct($model);
    }
}
