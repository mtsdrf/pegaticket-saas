<?php

namespace App\Services\Storefront;

use App\DTOs\Storefront\UpdateReactivationRuleDTO;
use App\Events\Storefront\ReactivationRuleUpdated;
use App\Models\Storefront\ReactivationRule;
use App\Repositories\Contracts\ReactivationRuleRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Régua de reativação (roadmap A5, item 18) — CRUD singleton por tenant,
 * mesmo padrão de TenantSettingsService. O processamento em si
 * (identificar clientes inativos, gerar cupom + push) fica em
 * ReactivationDispatchService, usado pelo comando reactivation:process.
 */
class ReactivationRuleService
{
    public function __construct(
        private ReactivationRuleRepositoryInterface $repository
    ) {
    }

    public function getForTenant(int $tenantId): ReactivationRule
    {
        return $this->repository->findOrCreateForTenant($tenantId);
    }

    public function update(int $tenantId, UpdateReactivationRuleDTO $dto): ReactivationRule
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $rule = $this->repository->findOrCreateForTenant($tenantId);

            $rule = $this->repository->update($rule, [
                'days_without_order' => $dto->daysWithoutOrder,
                'coupon_type' => $dto->couponType,
                'coupon_value' => $dto->couponValue,
                'coupon_validity_days' => $dto->couponValidityDays,
                'is_active' => $dto->isActive,
            ]);

            event(new ReactivationRuleUpdated(tenantId: $tenantId, actorId: Auth::id()));

            return $rule;
        });
    }
}
