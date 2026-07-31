<?php

namespace App\Repositories\Eloquent;

use App\Models\Client\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;

class ClientRepository extends BaseRepository implements ClientRepositoryInterface
{
    public function __construct(Client $model)
    {
        parent::__construct($model);
    }
}
