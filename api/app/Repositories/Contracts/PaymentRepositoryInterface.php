<?php

namespace App\Repositories\Contracts;

use App\Models\Subscription\Payment;

interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function findByIdempotencyKey(string $key): ?Payment;
}
