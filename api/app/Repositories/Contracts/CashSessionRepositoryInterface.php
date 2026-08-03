<?php

namespace App\Repositories\Contracts;

use App\Models\CashSession\CashSession;
use Illuminate\Pagination\LengthAwarePaginator;

interface CashSessionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Caixa aberto do tenant (no máximo um por vez), ou null. Lock
     * pessimista opcional para abrir/fechar com segurança sob concorrência.
     */
    public function findOpenForTenant(int $tenantId, bool $lock = false): ?CashSession;

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator;
}
