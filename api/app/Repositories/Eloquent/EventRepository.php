<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\Event;
use App\Repositories\Contracts\EventRepositoryInterface;

class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    public function __construct(Event $model)
    {
        parent::__construct($model);
    }
}
