<?php

namespace App\Services\FinalCustomer;

use App\DTOs\FinalCustomer\CrmFinalCustomerFilterDTO;
use App\DTOs\FinalCustomer\SearchFinalCustomerDTO;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Busca de compradores (FinalCustomerTenantLink) pro staff — fluxo de
 * venda manual (SaleFormPage), equivalente ao antigo GET /clients do
 * ClientController removido. Read-only, sem Event/auditoria (mesmo padrão
 * de ProductTypeService::paginate() / ProductCategoryService::paginate()).
 */
class FinalCustomerService
{
    public function __construct(
        private FinalCustomerTenantLinkRepositoryInterface $repository
    ) {}

    public function paginate(int $tenantId, SearchFinalCustomerDTO $dto): LengthAwarePaginator
    {
        return $this->repository->searchActiveForTenant(
            $tenantId,
            $dto->search,
            $dto->perPage
        );
    }

    /**
     * CRM básico (Fase 6, fatia final) — agregação de total gasto/compras/
     * última compra por comprador, com filtros de segmentação simples.
     */
    public function crm(int $tenantId, CrmFinalCustomerFilterDTO $dto): LengthAwarePaginator
    {
        return $this->repository->crmSummaryForTenant(
            $tenantId,
            $dto->search,
            $dto->minSpent,
            $dto->minPurchases,
            $dto->inactiveDays,
            $dto->perPage
        );
    }
}
