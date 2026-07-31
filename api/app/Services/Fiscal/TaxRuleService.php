<?php

namespace App\Services\Fiscal;

use App\DTOs\Fiscal\CreateTaxRuleDTO;
use App\DTOs\Fiscal\UpdateTaxRuleDTO;
use App\Events\Fiscal\TaxRuleCreated;
use App\Events\Fiscal\TaxRuleDeleted;
use App\Events\Fiscal\TaxRuleUpdated;
use App\Models\Fiscal\TaxRule;
use App\Repositories\Contracts\TaxRuleRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de regra tributária parametrizada e versionada (roadmap Fiscal D0),
 * mesmo padrão de CouponService. NÃO há motor de cálculo de imposto sobre
 * pedido aqui — só o cadastro versionado, pronto pra um motor plugar depois.
 * Todo acesso é tenant-scoped (isolamento multi-tenant garantido em cada
 * query por `tenant_id`).
 */
class TaxRuleService
{
    public function __construct(
        private TaxRuleRepositoryInterface $repository
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function create(int $tenantId, CreateTaxRuleDTO $dto): TaxRule
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $taxRule = $this->repository->create([
                'tenant_id' => $tenantId,
                'tax_type' => $dto->taxType,
                'scope' => $dto->scope,
                'rate_percent' => $dto->ratePercent,
                'valid_from' => $dto->validFrom,
                'valid_to' => $dto->validTo,
                'is_active' => $dto->isActive,
            ]);

            event(new TaxRuleCreated(taxRuleUuid: $taxRule->uuid, actorId: Auth::id()));

            return $taxRule;
        });
    }

    public function update(int $tenantId, string $uuid, UpdateTaxRuleDTO $dto): TaxRule
    {
        return DB::transaction(function () use ($tenantId, $uuid, $dto) {
            $taxRule = $this->findScopedOrFail($tenantId, $uuid);

            $taxRule = $this->repository->update($taxRule, [
                'tax_type' => $dto->taxType,
                'scope' => $dto->scope,
                'rate_percent' => $dto->ratePercent,
                'valid_from' => $dto->validFrom,
                'valid_to' => $dto->validTo,
                'is_active' => $dto->isActive,
            ]);

            event(new TaxRuleUpdated(taxRuleUuid: $taxRule->uuid, actorId: Auth::id()));

            return $taxRule;
        });
    }

    public function delete(int $tenantId, string $uuid): void
    {
        DB::transaction(function () use ($tenantId, $uuid) {
            $taxRule = $this->findScopedOrFail($tenantId, $uuid);

            $this->repository->delete($taxRule);

            event(new TaxRuleDeleted(taxRuleUuid: $uuid, actorId: Auth::id()));
        });
    }

    /**
     * Resolve por uuid SEMPRE dentro do tenant ativo — impede que um tenant
     * edite/apague regra de outro tenant só sabendo o uuid (IDOR).
     */
    private function findScopedOrFail(int $tenantId, string $uuid): TaxRule
    {
        return TaxRule::where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }
}
