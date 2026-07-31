<?php

namespace App\Repositories\Contracts;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use Illuminate\Support\Collection;

/**
 * Contrato dedicado, NÃO estende BaseRepositoryInterface — mesmo motivo de
 * FinalCustomerRepositoryInterface (`final_customer_tenant_links` não tem
 * `deleted_at`).
 */
interface FinalCustomerTenantLinkRepositoryInterface
{
    public function findByCustomerAndClient(int $finalCustomerId, int $clientId): ?FinalCustomerTenantLink;

    /**
     * Reverse lookup (roadmap Delivery, Fase 4 — Web Push): a partir de
     * tenant_id+client_id de um Order, resolve o vínculo CONFIRMADO (se
     * existir) para descobrir qual FinalCustomer notificar. Silencioso
     * (null) quando não há vínculo confirmado — não é erro.
     */
    public function findConfirmedByTenantAndClient(int $tenantId, int $clientId): ?FinalCustomerTenantLink;

    public function create(array $data): FinalCustomerTenantLink;

    /**
     * Vínculos confirmados do cliente final, com tenant/client carregados.
     */
    public function confirmedLinksFor(int $finalCustomerId): Collection;
}
