<?php

namespace App\Repositories\Eloquent;

use App\Models\CommunicationLog;
use App\Repositories\Contracts\CommunicationLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CommunicationLogRepository implements CommunicationLogRepositoryInterface
{
    private const SORTABLE = [
        'type' => 'type',
        'status' => 'status',
        'recipient_email' => 'recipient_email',
        'created_at' => 'created_at',
    ];

    public function create(array $data): CommunicationLog
    {
        return CommunicationLog::create($data);
    }

    public function paginate(
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = CommunicationLog::query()->with('tenant');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['recipient_email'])) {
            $query->where('recipient_email', 'like', '%'.$filters['recipient_email'].'%');
        }

        $sortColumn = is_string($sortBy) ? (self::SORTABLE[$sortBy] ?? null) : null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }
}
