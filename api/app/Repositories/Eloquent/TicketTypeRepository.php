<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\TicketType;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;

class TicketTypeRepository extends BaseRepository implements TicketTypeRepositoryInterface
{
    public function __construct(TicketType $model)
    {
        parent::__construct($model);
    }
}
