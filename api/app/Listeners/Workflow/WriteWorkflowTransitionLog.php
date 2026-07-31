<?php

namespace App\Listeners\Workflow;

use App\Events\Order\OrderApproved;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderCreated;
use App\Events\Order\OrderDelivered;
use App\Events\Order\OrderOutForDelivery;
use App\Events\Order\OrderRejected;
use App\Events\Order\OrderUndispatched;
use App\Models\Order\Order;
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
            $event instanceof OrderCreated => $this->handleOrderCreated($event),
            $event instanceof OrderApproved => $this->handleOrderApproved($event),
            $event instanceof OrderRejected => $this->handleOrderRejected($event),
            $event instanceof OrderOutForDelivery => $this->handleOrderOutForDelivery($event),
            $event instanceof OrderUndispatched => $this->handleOrderUndispatched($event),
            $event instanceof OrderDelivered => $this->handleOrderDelivered($event),
            $event instanceof OrderCancelled => $this->handleOrderCancelled($event),
            default => null,
        };
    }

    private function handleOrderCreated(OrderCreated $event): void
    {
        $order = Order::query()->find($event->orderId);

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

    private function handleOrderApproved(OrderApproved $event): void
    {
        $order = Order::query()->find($event->orderId);

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

    private function handleOrderRejected(OrderRejected $event): void
    {
        $order = Order::query()->find($event->orderId);

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

    private function handleOrderOutForDelivery(OrderOutForDelivery $event): void
    {
        $order = Order::query()->find($event->orderId);

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

    private function handleOrderUndispatched(OrderUndispatched $event): void
    {
        $order = Order::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        $this->logger->recordOrderTransition(
            order: $order,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'undo',
            actorId: $event->actorId,
        );
    }

    private function handleOrderDelivered(OrderDelivered $event): void
    {
        $order = Order::query()->find($event->orderId);

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

    private function handleOrderCancelled(OrderCancelled $event): void
    {
        $order = Order::query()->find($event->orderId);

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
