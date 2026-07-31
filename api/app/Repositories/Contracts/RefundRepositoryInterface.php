<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RefundRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Estornos do tenant (pedido pago cancelado, arrependimento de
     * assinatura, contestação/chargeback) — visão consolidada do
     * proprietário, mais recentes primeiro.
     */
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator;
}
