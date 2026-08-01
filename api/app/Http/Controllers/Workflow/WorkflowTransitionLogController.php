<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Resources\Workflow\WorkflowTransitionLogResource;
use App\Models\Sale\Sale;
use App\Services\APIResponse;
use App\Services\Workflow\WorkflowTransitionLogService;

class WorkflowTransitionLogController extends Controller
{
    public function __construct(
        private WorkflowTransitionLogService $service
    ) {
    }

    public function sale(Sale $sale)
    {
        $logs = $this->service->listOrderTimeline($sale);

        return APIResponse::success(
            WorkflowTransitionLogResource::collection($logs),
            __('messages.workflow.timeline_list')
        );
    }

}
