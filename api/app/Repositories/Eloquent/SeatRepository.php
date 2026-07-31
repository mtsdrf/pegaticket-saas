<?php

namespace App\Repositories\Eloquent;

use App\Models\Venue\Seat;
use App\Repositories\Contracts\SeatRepositoryInterface;

class SeatRepository extends BaseRepository implements SeatRepositoryInterface
{
    public function __construct(Seat $model)
    {
        parent::__construct($model);
    }
}
