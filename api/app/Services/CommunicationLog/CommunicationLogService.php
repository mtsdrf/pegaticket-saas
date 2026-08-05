<?php

namespace App\Services\CommunicationLog;

use App\Repositories\Contracts\CommunicationLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CommunicationLogService
{
    public function __construct(
        private CommunicationLogRepositoryInterface $repository
    ) {}

    public function paginate(
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        return $this->repository->paginate($filters, $sortBy, $sortDir, $perPage);
    }
}
