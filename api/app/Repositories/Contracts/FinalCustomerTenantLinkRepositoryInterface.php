<?php

namespace App\Repositories\Contracts;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Contrato dedicado, NÃO estende BaseRepositoryInterface — mesmo motivo de
 * FinalCustomerRepositoryInterface (`final_customer_tenant_links` não tem
 * `deleted_at`). É o registro POR-TENANT do cliente final (absorveu
 * App\Models\Client\Client, descontinuado em 2026-07-31).
 */
interface FinalCustomerTenantLinkRepositoryInterface
{
    public function findByCustomerAndTenant(int $finalCustomerId, int $tenantId): ?FinalCustomerTenantLink;

    /**
     * Reverse lookup (roadmap Delivery, Fase 4 — Web Push): a partir de
     * tenant_id+final_customer_id de um Sale, resolve o vínculo
     * CONFIRMADO (se existir). Silencioso (null) quando não há vínculo
     * confirmado — não é erro.
     */
    public function findConfirmedByTenantAndFinalCustomer(int $tenantId, int $finalCustomerId): ?FinalCustomerTenantLink;

    public function create(array $data): FinalCustomerTenantLink;

    public function updateOrCreateLink(int $finalCustomerId, int $tenantId, array $data): FinalCustomerTenantLink;

    /**
     * Vínculos confirmados do cliente final, com tenant carregado.
     */
    public function confirmedLinksFor(int $finalCustomerId): Collection;

    /**
     * Busca paginada de compradores ativos do tenant pro staff (fluxo de
     * venda manual, SaleFormPage). $search casa por LIKE contra nome/
     * email do FinalCustomer relacionado e cpf_cnpj/phone_primary do link.
     */
    public function searchActiveForTenant(int $tenantId, ?string $search, int $perPage): LengthAwarePaginator;
}
