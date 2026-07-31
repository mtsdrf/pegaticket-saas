<?php

namespace App\Services\Workflow;

use App\Models\Balcao\Comanda;
use App\Models\Balcao\ComandaItem;
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

    public function listComandaItemTimeline(string $comandaUuid, string $itemUuid, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $tenantId = (int) app('tenant_id');

        $comanda = Comanda::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $comandaUuid)
            ->first();

        if (!$comanda) {
            throw (new ModelNotFoundException())->setModel(Comanda::class, [$comandaUuid]);
        }

        $item = ComandaItem::query()
            ->where('tenant_id', $tenantId)
            ->where('comanda_id', $comanda->id)
            ->where('uuid', $itemUuid)
            ->first();

        if (!$item) {
            throw (new ModelNotFoundException())->setModel(ComandaItem::class, [$itemUuid]);
        }

        return WorkflowTransitionLog::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->where('workflow_type', 'comanda_item')
            ->where('entity_uuid', $item->uuid)
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
