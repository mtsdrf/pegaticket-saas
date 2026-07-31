<?php

namespace App\Repositories\Eloquent;

use App\Models\Storefront\CartEvent;
use App\Repositories\Contracts\CartEventRepositoryInterface;

class CartEventRepository implements CartEventRepositoryInterface
{
    public function __construct(
        private CartEvent $model
    ) {
    }

    public function store(array $data): CartEvent
    {
        return $this->model->create($data);
    }
}
