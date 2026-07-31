<?php

namespace App\Services\Fiscal;

use App\Exceptions\Fiscal\FiscalPreparationBlockedException;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Order\Order;

class FiscalDocumentStatusSyncService
{
    public function __construct(
        private FiscalDocumentLifecycleService $lifecycleService,
        private FiscalProviderRegistry $providerRegistry,
        private FiscalProviderMessageRecorder $messageRecorder,
        private FiscalDocumentAttemptRecorder $attemptRecorder,
    ) {
    }

    public function sync(Order $order): FiscalDocument
    {
        $document = $this->lifecycleService->latestSyncableDocument($order);

        if (blank($document->provider_document_id)) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_document_provider_reference_missing'),
                [[
                    'key' => 'fiscal_document_provider_reference_missing',
                    'label' => 'Documento fiscal',
                    'severity' => 'error',
                    'details' => __('messages.order.fiscal_document_provider_reference_missing'),
                ]]
            );
        }

        $response = $this->providerRegistry
            ->forDocument($document)
            ->getStatus((string) $document->provider_document_id);

        $this->applyProviderStatus($document, $response);

        $freshDocument = $document->fresh();

        $this->attemptRecorder->record(
            $freshDocument,
            'sync_status',
            'succeeded',
            [
                'document_uuid' => $freshDocument->uuid,
                'provider_document_id' => $freshDocument->provider_document_id,
                'previous_status' => $document->status,
            ],
            $response,
        );

        $this->messageRecorder->record(
            $freshDocument,
            'status_sync',
            __('messages.order.fiscal_provider_status_sync_recorded'),
            $response,
        );

        return $freshDocument;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function applyProviderStatus(FiscalDocument $document, array $response): void
    {
        $resolvedStatus = $this->normalizeProviderStatus((string) ($response['status'] ?? $document->status));
        $now = now();

        $updates = [
            'provider' => $response['provider'] ?? $document->provider,
            'provider_document_id' => $response['provider_document_id'] ?? $document->provider_document_id,
            'status' => $resolvedStatus,
            'access_key' => $response['access_key'] ?? $document->access_key,
            'xml_content' => $response['xml_content'] ?? $document->xml_content,
            'pdf_path' => $response['pdf_path'] ?? $document->pdf_path,
            'provider_response_payload' => $response,
            'provider_status_checked_at' => $now,
        ];

        if ($resolvedStatus === 'authorized' && !$document->authorized_at) {
            $updates['authorized_at'] = $now;
            $updates['rejected_at'] = null;
            $updates['rejection_reason'] = null;
        }

        if ($resolvedStatus === 'rejected') {
            $updates['rejected_at'] = $document->rejected_at ?: $now;
            $updates['rejection_reason'] = $response['reason'] ?? $response['message'] ?? $document->rejection_reason;
            $updates['canceled_at'] = null;
        }

        if ($resolvedStatus === 'canceled') {
            $updates['canceled_at'] = $document->canceled_at ?: $now;
            $updates['rejection_reason'] = $response['reason'] ?? $response['message'] ?? $document->rejection_reason;
        }

        $document->fill($updates)->save();
    }

    private function normalizeProviderStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'authorized', 'approved' => 'authorized',
            'rejected', 'denied', 'error' => 'rejected',
            'canceled', 'cancelled' => 'canceled',
            'pending', 'processing', 'in_progress' => 'pending',
            'provider_submitted', 'submitted' => 'provider_submitted',
            default => $providerStatus,
        };
    }
}
