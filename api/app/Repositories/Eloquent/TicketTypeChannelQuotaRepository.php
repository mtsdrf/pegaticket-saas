<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\TicketTypeChannelQuota;
use App\Repositories\Contracts\TicketTypeChannelQuotaRepositoryInterface;

class TicketTypeChannelQuotaRepository extends BaseRepository implements TicketTypeChannelQuotaRepositoryInterface
{
    public function __construct(TicketTypeChannelQuota $model)
    {
        parent::__construct($model);
    }
}
