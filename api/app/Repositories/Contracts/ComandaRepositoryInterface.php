<?php

namespace App\Repositories\Contracts;

use App\Models\Balcao\Comanda;
use Illuminate\Support\Collection;

interface ComandaRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Comandas abertas do tenant (fila operacional do garçom), mais recentes
     * primeiro, com itens e mesa carregados.
     */
    public function listOpenForTenant(int $tenantId): Collection;

    public function findByUuidForTenant(string $uuid, int $tenantId): ?Comanda;

    /**
     * Existe outra comanda ainda aberta (open|closing) na mesma mesa, além
     * da que está sendo fechada? Usado para decidir se a mesa é liberada.
     */
    public function hasOtherOpenComandaOnTable(int $tableId, int $excludeComandaId): bool;
}
