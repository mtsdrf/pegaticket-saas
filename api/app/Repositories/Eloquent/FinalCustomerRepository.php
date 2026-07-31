<?php

namespace App\Repositories\Eloquent;

use App\Models\FinalCustomer\FinalCustomer;
use App\Repositories\Contracts\FinalCustomerRepositoryInterface;

class FinalCustomerRepository implements FinalCustomerRepositoryInterface
{
    public function __construct(private FinalCustomer $model)
    {
    }

    public function findByEmail(string $email): ?FinalCustomer
    {
        return $this->model->where('email', $email)->first();
    }

    public function findById(int $id): ?FinalCustomer
    {
        return $this->model->find($id);
    }

    public function create(array $data): FinalCustomer
    {
        return $this->model->create($data);
    }
}
