<?php

namespace App\Services\Workflow;

use App\Models\Balcao\ComandaItem;
use App\Models\Order\Order;
use App\Models\Workflow\WorkflowTransitionLog;
use Carbon\CarbonInterface;

class WorkflowTransitionLogger
{
    public function recordOrderTransition(
        Order $order,
        ?string $fromStage,
        ?string $toStage = null,
        string $transitionType = 'move',
        ?string $reason = null,
        ?int $actorId = null,
        array $meta = [],
        ?CarbonInterface $movedAt = null,
    ): void {
        $resolvedToStage = $toStage ?? $this->resolveOrderStage($order);

        $this->record(
            tenantId: (int) $order->tenant_id,
            actorId: $actorId,
            workflowType: 'order',
            entityId: (int) $order->id,
            entityUuid: $order->uuid,
            fromStage: $fromStage,
            toStage: $resolvedToStage,
            transitionType: $transitionType,
            reason: $reason,
            meta: $meta,
            movedAt: $movedAt,
        );
    }

    public function recordComandaItemTransition(
        ComandaItem $item,
        ?string $fromStage,
        ?string $toStage = null,
        string $transitionType = 'move',
        ?string $reason = null,
        ?int $actorId = null,
        array $meta = [],
        ?CarbonInterface $movedAt = null,
    ): void {
        $resolvedToStage = $toStage ?? $this->resolveComandaItemStage($item);

        $this->record(
            tenantId: (int) $item->tenant_id,
            actorId: $actorId,
            workflowType: 'comanda_item',
            entityId: (int) $item->id,
            entityUuid: $item->uuid,
            fromStage: $fromStage,
            toStage: $resolvedToStage,
            transitionType: $transitionType,
            reason: $reason,
            meta: $meta,
            movedAt: $movedAt,
        );
    }

    public function resolveOrderStage(Order $order): string
    {
        if ($order->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($order->status === 'rejected') {
            return 'rejected';
        }

        if ($order->status === 'cancellation_requested') {
            return 'cancellation_requested';
        }

        if ($order->status === 'pending_approval') {
            return 'approval';
        }

        if ($order->is_delivered && !$order->is_paid) {
            return 'financial_pending';
        }

        if ($order->is_delivered && $order->is_paid) {
            return 'completed';
        }

        if ($order->is_out_for_delivery) {
            return 'dispatch';
        }

        return 'production';
    }

    public function resolveComandaItemStage(ComandaItem $item): string
    {
        return $item->prep_status;
    }

    private function record(
        int $tenantId,
        ?int $actorId,
        string $workflowType,
        int $entityId,
        string $entityUuid,
        ?string $fromStage,
        string $toStage,
        string $transitionType,
        ?string $reason,
        array $meta,
        ?CarbonInterface $movedAt,
    ): void {
        WorkflowTransitionLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $actorId,
            'workflow_type' => $workflowType,
            'entity_id' => $entityId,
            'entity_uuid' => $entityUuid,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'transition_type' => $transitionType,
            'reason' => $reason,
            'meta' => $meta,
            'moved_at' => $movedAt ?? now(),
        ]);
    }
}
