<?php

namespace App\Services\Subscription;

use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Visão do PRÓPRIO tenant sobre seus estornos (pedido pago cancelado,
 * arrependimento de assinatura, contestação) — roadmap 2026-07-24.
 */
class RefundService
{
    public function __construct(
        private RefundRepositoryInterface $repository,
    ) {
    }

    public function listForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForTenant($tenantId, $perPage);
    }
}
