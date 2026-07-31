<?php

namespace App\Http\Controllers\Order;

use App\Exceptions\Fiscal\FiscalPreparationBlockedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Fiscal\FiscalDocumentDetailResource;
use App\Http\Resources\Fiscal\FiscalDocumentResource;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Order\Order;
use App\Services\APIResponse;
use App\Services\Fiscal\FiscalDocumentLifecycleService;
use App\Services\Fiscal\FiscalDocumentXmlPreviewBuilder;
use App\Services\Fiscal\FiscalDocumentStatusSyncService;
use App\Services\Fiscal\OrderFiscalPreparationService;
use App\Services\Fiscal\OrderFiscalSubmissionService;
use App\Services\Order\OrderService;

class OrderFiscalDocumentController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderFiscalPreparationService $preparationService,
        private OrderFiscalSubmissionService $submissionService,
        private FiscalDocumentStatusSyncService $statusSyncService,
        private FiscalDocumentLifecycleService $lifecycleService,
        private FiscalDocumentXmlPreviewBuilder $xmlPreviewBuilder,
    ) {
    }

    public function store(Order $order)
    {
        $order = $this->orderService->find($order);

        try {
            $document = $this->preparationService->prepare($order);
        } catch (FiscalPreparationBlockedException $e) {
            return APIResponse::error(
                $e->getMessage(),
                422,
                'FISCAL_PREPARATION_BLOCKED',
                ['issues' => $e->issues()],
            );
        }

        return APIResponse::success(
            new FiscalDocumentResource($document),
            __('messages.order.fiscal_document_prepared'),
            201
        );
    }

    public function show(Order $order)
    {
        $order = $this->orderService->find($order);

        /** @var FiscalDocument|null $document */
        $document = $order->fiscalDocuments()
            ->with([
                'providerMessages' => fn ($query) => $query->latest('id'),
                'attempts' => fn ($query) => $query->latest('id'),
            ])
            ->latest('id')
            ->first();

        if (!$document) {
            return APIResponse::error(
                __('messages.order.fiscal_document_not_found'),
                404,
                'FISCAL_DOCUMENT_NOT_FOUND'
            );
        }

        return APIResponse::success(
            new FiscalDocumentDetailResource($document),
            __('messages.order.fiscal_document_shown')
        );
    }

    public function cancel(Order $order)
    {
        $order = $this->orderService->find($order);

        try {
            $document = $this->lifecycleService->cancelPreparedDocument($order);
        } catch (FiscalPreparationBlockedException $e) {
            return APIResponse::error(
                $e->getMessage(),
                422,
                'FISCAL_DOCUMENT_CANCEL_UNAVAILABLE',
                ['issues' => $e->issues()],
            );
        }

        return APIResponse::success(
            new FiscalDocumentResource($document),
            __('messages.order.fiscal_document_canceled')
        );
    }

    public function submit(Order $order)
    {
        $order = $this->orderService->find($order);

        try {
            $document = $this->submissionService->submit($order);
        } catch (FiscalPreparationBlockedException $e) {
            return APIResponse::error(
                $e->getMessage(),
                422,
                'FISCAL_SUBMISSION_BLOCKED',
                ['issues' => $e->issues()],
            );
        }

        return APIResponse::success(
            new FiscalDocumentResource($document),
            __('messages.order.fiscal_document_submitted')
        );
    }

    public function syncStatus(Order $order)
    {
        $order = $this->orderService->find($order);

        try {
            $document = $this->statusSyncService->sync($order);
        } catch (FiscalPreparationBlockedException $e) {
            return APIResponse::error(
                $e->getMessage(),
                422,
                'FISCAL_STATUS_SYNC_BLOCKED',
                ['issues' => $e->issues()],
            );
        }

        return APIResponse::success(
            new FiscalDocumentResource($document),
            __('messages.order.fiscal_document_status_synced')
        );
    }

    public function xmlPreview(Order $order)
    {
        $order = $this->orderService->find($order);

        /** @var FiscalDocument|null $document */
        $document = $order->fiscalDocuments()
            ->latest('id')
            ->first();

        if (!$document) {
            return APIResponse::error(
                __('messages.order.fiscal_document_not_found'),
                404,
                'FISCAL_DOCUMENT_NOT_FOUND'
            );
        }

        $filename = sprintf(
            'pedido-%s-rascunho-fiscal.xml',
            $order->codigo ?: $order->uuid
        );

        return response($this->xmlPreviewBuilder->build($document), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
