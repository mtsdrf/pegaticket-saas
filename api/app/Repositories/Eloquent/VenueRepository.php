<?php

namespace App\Repositories\Eloquent;

use App\Models\Venue\Venue;
use App\Repositories\Contracts\VenueRepositoryInterface;

class VenueRepository extends BaseRepository implements VenueRepositoryInterface
{
    public function __construct(Venue $model)
    {
        parent::__construct($model);
    }
}
