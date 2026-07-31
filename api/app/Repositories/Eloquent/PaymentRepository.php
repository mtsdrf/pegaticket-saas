<?php

namespace App\Repositories\Eloquent;

use App\Models\Subscription\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    public function findByIdempotencyKey(string $key): ?Payment
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('idempotency_key', $key)
            ->first();
    }
}
