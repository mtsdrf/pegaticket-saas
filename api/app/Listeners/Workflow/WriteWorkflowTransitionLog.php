<?php

namespace App\Listeners\Workflow;

use App\Events\Sale\SaleApproved;
use App\Events\Sale\SaleCancelled;
use App\Events\Sale\SaleCreated;
use App\Events\Sale\SaleDelivered;
use App\Events\Sale\SaleRejected;
use App\Models\Sale\Sale;
use App\Services\Workflow\WorkflowTransitionLogger;

class WriteWorkflowTransitionLog
{
    public function __construct(
        private WorkflowTransitionLogger $logger,
    ) {
    }

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof SaleCreated => $this->handleOrderCreated($event),
            $event instanceof SaleApproved => $this->handleOrderApproved($event),
            $event instanceof SaleRejected => $this->handleOrderRejected($event),
            $event instanceof SaleDelivered => $this->handleOrderDelivered($event),
            $event instanceof SaleCancelled => $this->handleOrderCancelled($event),
            default => null,
        };
    }

    private function handleOrderCreated(SaleCreated $event): void
    {
        $order = Sale::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        $this->logger->recordOrderTransition(
            order: $order,
            fromStage: null,
            transitionType: 'create',
            actorId: $event->actorId,
            meta: [
                'origin' => $order->origin,
                'status' => $order->status,
            ],
        );
    }

    private function handleOrderApproved(SaleApproved $event): void
    {
        $order = Sale::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        $this->logger->recordOrderTransition(
            order: $order,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'move',
            actorId: $event->actorId,
        );
    }

    private function handleOrderRejected(SaleRejected $event): void
    {
        $order = Sale::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        $this->logger->recordOrderTransition(
            order: $order,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'reject',
            reason: $event->reason,
            actorId: $event->actorId,
        );
    }

    private function handleOrderDelivered(SaleDelivered $event): void
    {
        $order = Sale::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        $this->logger->recordOrderTransition(
            order: $order,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'move',
            actorId: $event->actorId,
        );
    }

    private function handleOrderCancelled(SaleCancelled $event): void
    {
        $order = Sale::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        $this->logger->recordOrderTransition(
            order: $order,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'cancel',
            reason: $event->cancellationReason,
            actorId: $event->actorId,
        );
    }
}
