<?php

namespace App\Services\Workflow;

use App\Models\Sale\Sale;
use App\Models\Workflow\WorkflowTransitionLog;
use Carbon\CarbonInterface;

class WorkflowTransitionLogger
{
    public function recordSaleTransition(
        Sale $sale,
        ?string $fromStage,
        ?string $toStage = null,
        string $transitionType = 'move',
        ?string $reason = null,
        ?int $actorId = null,
        array $meta = [],
        ?CarbonInterface $movedAt = null,
    ): void {
        $resolvedToStage = $toStage ?? $this->resolveSaleStage($sale);

        $this->record(
            tenantId: (int) $sale->tenant_id,
            actorId: $actorId,
            workflowType: 'sale',
            entityId: (int) $sale->id,
            entityUuid: $sale->uuid,
            fromStage: $fromStage,
            toStage: $resolvedToStage,
            transitionType: $transitionType,
            reason: $reason,
            meta: $meta,
            movedAt: $movedAt,
        );
    }

    public function resolveSaleStage(Sale $sale): string
    {
        if ($sale->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($sale->status === 'rejected') {
            return 'rejected';
        }

        if ($sale->status === 'cancellation_requested') {
            return 'cancellation_requested';
        }

        if ($sale->status === 'pending_approval') {
            return 'approval';
        }

        if ($sale->is_paid) {
            return 'completed';
        }

        return 'confirmed';
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
