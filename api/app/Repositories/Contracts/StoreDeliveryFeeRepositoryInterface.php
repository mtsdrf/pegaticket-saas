<?php

namespace App\Repositories\Contracts;

use App\Models\Storefront\StoreDeliveryFee;
use Illuminate\Support\Collection;

interface StoreDeliveryFeeRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;

    /**
     * Um bairro só pode ter 1 taxa por tenant — atualiza o valor em vez de
     * duplicar quando já existe (inclusive se a linha existente estava
     * soft-deletada, restaura em vez de colidir com a unique
     * (tenant_id, bairro_id)).
     */
    public function upsertForTenant(int $tenantId, int $bairroId, float $fee): StoreDeliveryFee;

    public function findFeeForTenantAndBairro(int $tenantId, string $bairroUuid): ?float;
}
