<?php

namespace App\Repositories\Contracts;

use App\Models\Storefront\Coupon;
use Illuminate\Support\Collection;

interface CouponRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;

    public function findActiveByCode(int $tenantId, string $code): ?Coupon;
}
