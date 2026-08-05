<?php

namespace App\Repositories\Eloquent;

use App\Models\Storefront\StorefrontFunnelEvent;
use App\Repositories\Contracts\FunnelEventRepositoryInterface;

class FunnelEventRepository implements FunnelEventRepositoryInterface
{
    public function __construct(
        private StorefrontFunnelEvent $model
    ) {}

    public function store(array $data): StorefrontFunnelEvent
    {
        return $this->model->create($data);
    }
}
