<?php

namespace App\Services\AuditLog;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService
{
    public function __construct(
        private AuditLogRepositoryInterface $repository
    ) {
    }

    public function paginate(
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        return $this->repository->paginate($filters, $sortBy, $sortDir, $perPage);
    }
}
