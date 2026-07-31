<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\EventSession;
use App\Repositories\Contracts\EventSessionRepositoryInterface;

class EventSessionRepository extends BaseRepository implements EventSessionRepositoryInterface
{
    public function __construct(EventSession $model)
    {
        parent::__construct($model);
    }
}
