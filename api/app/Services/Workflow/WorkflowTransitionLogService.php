<?php

namespace App\Services\Workflow;

use App\Models\Order\Order;
use App\Models\Workflow\WorkflowTransitionLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WorkflowTransitionLogService
{
    private const DEFAULT_LIMIT = 30;

    public function listOrderTimeline(Order $order, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $this->assertTenantOwnership((int) $order->tenant_id);

        return WorkflowTransitionLog::query()
            ->with('user')
            ->where('tenant_id', app('tenant_id'))
            ->where('workflow_type', 'order')
            ->where('entity_uuid', $order->uuid)
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->limit($this->normalizeLimit($limit))
            ->get();
    }

    private function assertTenantOwnership(int $tenantId): void
    {
        if ($tenantId !== (int) app('tenant_id')) {
            throw (new ModelNotFoundException())->setModel(Order::class);
        }
    }

    private function normalizeLimit(int $limit): int
    {
        return max(1, min($limit, 100));
    }
}
