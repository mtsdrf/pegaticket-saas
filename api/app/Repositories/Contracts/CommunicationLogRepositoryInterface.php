<?php

namespace App\Repositories\Contracts;

use App\Models\CommunicationLog;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface CommunicationLogRepositoryInterface
 *
 * communication_logs é somente leitura via API (só criado internamente via
 * CommunicationDispatcherService) e não tem soft delete/updated_at — por
 * isso não extends BaseRepositoryInterface, mesmo espírito de
 * AuditLogRepositoryInterface.
 */
interface CommunicationLogRepositoryInterface
{
    public function create(array $data): CommunicationLog;

    public function paginate(
        array $filters,
        ?string $sortBy,
        string $sortDir,
        int $perPage
    ): LengthAwarePaginator;
}
