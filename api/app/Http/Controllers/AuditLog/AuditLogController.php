<?php

namespace App\Http\Controllers\AuditLog;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\ListAuditLogRequest;
use App\Http\Resources\AuditLog\AuditLogResource;
use App\Services\APIResponse;
use App\Services\AuditLog\AuditLogService;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogService $service
    ) {
    }

    public function index(ListAuditLogRequest $request)
    {
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'event',
            'auditable_type',
            'user_name',
            'created_at_min',
            'created_at_max',
        ])->all();

        $list = $this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $filters,
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            AuditLogResource::collection($list),
            __('messages.audit_log.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }
}
