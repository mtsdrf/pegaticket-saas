<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * Whitelist de sort_by aceito pelo grid — user_name exige leftJoin com
     * users (audit_logs.user_id é nullable, join é left para não excluir
     * eventos sem ator, ex. auto-registro). select('audit_logs.*') evita
     * ambiguidade de coluna ("id"/"created_at" existem em users também).
     */
    private const SORTABLE = [
        'event' => 'audit_logs.event',
        'auditable_type' => 'audit_logs.auditable_type',
        'user_name' => 'users.name',
        'created_at' => 'audit_logs.created_at',
    ];

    public function paginate(
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $sortColumn = is_string($sortBy) ? (self::SORTABLE[$sortBy] ?? null) : null;
        $needsUserJoin = $sortColumn === 'users.name' || !empty($filters['user_name']);

        $query = AuditLog::query()
            ->select('audit_logs.*')
            ->with('user');

        if ($needsUserJoin) {
            $query->leftJoin('users', 'users.id', '=', 'audit_logs.user_id');
        }

        if (!empty($filters['event'])) {
            $query->where('audit_logs.event', 'like', '%' . $filters['event'] . '%');
        }

        if (!empty($filters['auditable_type'])) {
            $query->where('audit_logs.auditable_type', 'like', '%' . $filters['auditable_type'] . '%');
        }

        if (!empty($filters['user_name'])) {
            $query->where('users.name', 'like', '%' . $filters['user_name'] . '%');
        }

        if (array_key_exists('created_at_min', $filters) && $filters['created_at_min'] !== null && $filters['created_at_min'] !== '') {
            $query->whereDate('audit_logs.created_at', '>=', $filters['created_at_min']);
        }

        if (array_key_exists('created_at_max', $filters) && $filters['created_at_max'] !== null && $filters['created_at_max'] !== '') {
            $query->whereDate('audit_logs.created_at', '<=', $filters['created_at_max']);
        }

        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            // Sem sort_by explícito, log é mais útil do mais recente pro
            // mais antigo — diferente do fallback "asc" usado nos outros
            // grids do projeto (que ordenam por nome).
            $query->orderBy('audit_logs.created_at', 'desc');
        }

        return $query->paginate($perPage);
    }
}
