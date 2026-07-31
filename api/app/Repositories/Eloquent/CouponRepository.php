<?php

namespace App\Repositories\Eloquent;

use App\Models\Storefront\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Support\Collection;

class CouponRepository extends BaseRepository implements CouponRepositoryInterface
{
    public function __construct(Coupon $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get();
    }

    /**
     * `is_active=true` aqui é só o primeiro filtro grosso — o restante da
     * elegibilidade (janela de datas/mínimo/limites de uso) é checado por
     * CouponService::validateForCheckout(), que precisa de mensagens
     * distintas por motivo de falha.
     */
    public function findActiveByCode(int $tenantId, string $code): ?Coupon
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }
}
