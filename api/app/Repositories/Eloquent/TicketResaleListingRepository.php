<?php

namespace App\Repositories\Eloquent;

use App\Models\Ticket\TicketResaleListing;
use App\Repositories\Contracts\TicketResaleListingRepositoryInterface;

class TicketResaleListingRepository extends BaseRepository implements TicketResaleListingRepositoryInterface
{
    public function __construct(TicketResaleListing $model)
    {
        parent::__construct($model);
    }
}
