<?php

namespace App\Repositories\Contracts;

use App\Models\Subscription\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SubscriptionRepositoryInterface extends BaseRepositoryInterface
{
    public function findCurrentForTenant(int $tenantId): ?Subscription;

    public function findByUuidForTenant(string $uuid, int $tenantId): ?Subscription;

    /**
     * Histórico paginado de TODAS as assinaturas do tenant ao longo do
     * tempo (sem filtro de status/atual) — usado pela tela "Empresa" do
     * proprietário para visão completa do vínculo com o Maskats.
     */
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Assinaturas cuja tolerância de 7 dias por falha de cobrança já
     * venceu e ainda não foram suspensas/canceladas. Usado por
     * `subscriptions:enforce-grace-period`.
     *
     * @return Collection<int, Subscription>
     */
    public function pastGracePeriod(): Collection;
}
