<?php

namespace App\Http\Controllers\CommunicationLog;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommunicationLog\ListCommunicationLogRequest;
use App\Http\Resources\CommunicationLog\CommunicationLogResource;
use App\Services\APIResponse;
use App\Services\CommunicationLog\CommunicationLogService;

class CommunicationLogController extends Controller
{
    public function __construct(
        private CommunicationLogService $service
    ) {}

    public function index(ListCommunicationLogRequest $request)
    {
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'type',
            'status',
            'recipient_email',
        ])->all();

        $list = $this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $filters,
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            CommunicationLogResource::collection($list),
            __('messages.communication_log.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }
}
