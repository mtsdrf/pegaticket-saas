<?php

namespace App\Services\Fiscal;

use App\Exceptions\Fiscal\FiscalPreparationBlockedException;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Order\Order;

class FiscalDocumentLifecycleService
{
    public function assertOrderCanPrepare(Order $order): void
    {
        $submittedDocument = $order->fiscalDocuments()
            ->whereIn('status', ['provider_submitted', 'pending'])
            ->latest('id')
            ->first();

        if ($submittedDocument) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_prepare_submitted_exists'),
                [[
                    'key' => 'submitted_fiscal_document',
                    'label' => 'Documento fiscal submetido',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_prepare_submitted_exists'),
                ]]
            );
        }

        $authorizedDocument = $order->fiscalDocuments()
            ->where('status', 'authorized')
            ->latest('id')
            ->first();

        if ($authorizedDocument) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_prepare_authorized_exists'),
                [[
                    'key' => 'authorized_fiscal_document',
                    'label' => 'Documento fiscal autorizado',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_prepare_authorized_exists'),
                ]]
            );
        }
    }

    public function invalidatePreparedDocuments(Order $order, string $reason): void
    {
        $documents = $order->fiscalDocuments()
            ->whereIn('status', ['draft', 'provider_submitted', 'pending'])
            ->get();

        foreach ($documents as $document) {
            $this->markAsCanceled($document, $reason);
        }
    }

    public function cancelPreparedDocument(Order $order, ?string $reason = null): FiscalDocument
    {
        /** @var FiscalDocument|null $document */
        $document = $order->fiscalDocuments()
            ->whereIn('status', ['draft', 'provider_submitted', 'pending'])
            ->latest('id')
            ->first();

        if (!$document) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_document_cancel_unavailable'),
                [[
                    'key' => 'fiscal_document_cancel_unavailable',
                    'label' => 'Documento fiscal',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_document_cancel_unavailable'),
                ]]
            );
        }

        $this->markAsCanceled(
            $document,
            $reason ?: __('messages.order.fiscal_document_canceled_default_reason')
        );

        return $document->fresh();
    }

    public function assertOrderCanSubmit(Order $order): void
    {
        $authorizedDocument = $order->fiscalDocuments()
            ->where('status', 'authorized')
            ->latest('id')
            ->first();

        if ($authorizedDocument) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_submit_authorized_exists'),
                [[
                    'key' => 'authorized_fiscal_document',
                    'label' => 'Documento fiscal autorizado',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_submit_authorized_exists'),
                ]]
            );
        }
    }

    public function latestSubmittableDocument(Order $order): FiscalDocument
    {
        /** @var FiscalDocument|null $document */
        $document = $order->fiscalDocuments()
            ->whereIn('status', ['draft', 'provider_submitted', 'pending'])
            ->latest('id')
            ->first();

        if (!$document) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_document_submit_unavailable'),
                [[
                    'key' => 'fiscal_document_submit_unavailable',
                    'label' => 'Documento fiscal',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_document_submit_unavailable'),
                ]]
            );
        }

        if ($document->status === 'provider_submitted') {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_document_already_submitted'),
                [[
                    'key' => 'fiscal_document_already_submitted',
                    'label' => 'Documento fiscal',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_document_already_submitted'),
                ]]
            );
        }

        if ($document->status === 'pending') {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_document_already_pending'),
                [[
                    'key' => 'fiscal_document_already_pending',
                    'label' => 'Documento fiscal',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_document_already_pending'),
                ]]
            );
        }

        return $document;
    }

    public function latestSyncableDocument(Order $order): FiscalDocument
    {
        /** @var FiscalDocument|null $document */
        $document = $order->fiscalDocuments()
            ->whereIn('status', ['provider_submitted', 'pending', 'authorized', 'rejected'])
            ->latest('id')
            ->first();

        if (!$document) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_document_sync_unavailable'),
                [[
                    'key' => 'fiscal_document_sync_unavailable',
                    'label' => 'Documento fiscal',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_document_sync_unavailable'),
                ]]
            );
        }

        return $document;
    }

    private function markAsCanceled(FiscalDocument $document, string $reason): void
    {
        $document->fill([
            'status' => 'canceled',
            'canceled_at' => now(),
            'rejection_reason' => $reason,
        ])->save();
    }
}
