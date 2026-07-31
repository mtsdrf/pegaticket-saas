<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface AuditLogRepositoryInterface
 *
 * audit_logs é somente leitura via API (nunca criado/editado/excluído por
 * aqui, só internamente via AuditLog::record()) e não tem soft delete
 * (sem coluna deleted_at) — por isso este contrato não extends
 * BaseRepositoryInterface: os métodos herdados (all/find/update/delete/...)
 * assumem whereNull('deleted_at') em toda query e quebrariam nesta tabela.
 */
interface AuditLogRepositoryInterface
{
    public function paginate(
        array $filters,
        ?string $sortBy,
        string $sortDir,
        int $perPage
    ): LengthAwarePaginator;
}
