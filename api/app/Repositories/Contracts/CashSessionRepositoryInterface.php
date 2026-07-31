<?php

namespace App\Repositories\Contracts;

use App\Models\Pdv\CashSession;
use Illuminate\Support\Collection;

interface CashSessionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Sessões do tenant, mais recentes primeiro.
     */
    public function listForTenant(int $tenantId, int $limit = 50): Collection;

    /**
     * Sessão aberta do tenant (regra dura: no máximo uma por vez, garantida
     * no CashSessionService::open). Retorna null se o caixa está fechado.
     */
    public function openSessionForTenant(int $tenantId): ?CashSession;

    /**
     * Sessão aberta de um cash_register específico, se houver — usada no
     * guard de "não abrir duas sessões pro mesmo registrador".
     */
    public function openSessionForRegister(int $cashRegisterId): ?CashSession;

    public function findByUuidForTenant(string $uuid, int $tenantId): ?CashSession;
}
