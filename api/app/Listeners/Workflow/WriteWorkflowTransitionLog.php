<?php

namespace App\Listeners\Workflow;

use App\Events\Sale\SaleApproved;
use App\Events\Sale\SaleCancelled;
use App\Events\Sale\SaleCreated;
use App\Events\Sale\SaleCompleted;
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
            $event instanceof SaleCreated => $this->handleSaleCreated($event),
            $event instanceof SaleApproved => $this->handleSaleApproved($event),
            $event instanceof SaleRejected => $this->handleSaleRejected($event),
            $event instanceof SaleCompleted => $this->handleSaleCompleted($event),
            $event instanceof SaleCancelled => $this->handleSaleCancelled($event),
            default => null,
        };
    }

    private function handleSaleCreated(SaleCreated $event): void
    {
        $sale = Sale::query()->find($event->saleId);

        if ($sale === null) {
            return;
        }

        $this->logger->recordSaleTransition(
            sale: $sale,
            fromStage: null,
            transitionType: 'create',
            actorId: $event->actorId,
            meta: [
                'origin' => $sale->origin,
                'status' => $sale->status,
            ],
        );
    }

    private function handleSaleApproved(SaleApproved $event): void
    {
        $sale = Sale::query()->find($event->saleId);

        if ($sale === null) {
            return;
        }

        $this->logger->recordSaleTransition(
            sale: $sale,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'move',
            actorId: $event->actorId,
        );
    }

    private function handleSaleRejected(SaleRejected $event): void
    {
        $sale = Sale::query()->find($event->saleId);

        if ($sale === null) {
            return;
        }

        $this->logger->recordSaleTransition(
            sale: $sale,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'reject',
            reason: $event->reason,
            actorId: $event->actorId,
        );
    }

    private function handleSaleCompleted(SaleCompleted $event): void
    {
        $sale = Sale::query()->find($event->saleId);

        if ($sale === null) {
            return;
        }

        $this->logger->recordSaleTransition(
            sale: $sale,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'move',
            actorId: $event->actorId,
        );
    }

    private function handleSaleCancelled(SaleCancelled $event): void
    {
        $sale = Sale::query()->find($event->saleId);

        if ($sale === null) {
            return;
        }

        $this->logger->recordSaleTransition(
            sale: $sale,
            fromStage: $event->fromStage,
            toStage: $event->toStage,
            transitionType: 'cancel',
            reason: $event->cancellationReason,
            actorId: $event->actorId,
        );
    }
}
