<?php

namespace App\Services\Fiscal;

use App\Exceptions\Fiscal\FiscalPreparationBlockedException;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;

class OrderFiscalSubmissionService
{
    public function __construct(
        private FiscalDocumentLifecycleService $lifecycleService,
        private FiscalProviderRegistry $providerRegistry,
        private FiscalProviderReadinessService $providerReadinessService,
        private FiscalProviderMessageRecorder $messageRecorder,
        private FiscalDocumentAttemptRecorder $attemptRecorder,
    ) {
    }

    public function submit(Order $order): FiscalDocument
    {
        $this->lifecycleService->assertOrderCanSubmit($order);

        $document = $this->lifecycleService->latestSubmittableDocument($order);
        $tenant = Tenant::query()->findOrFail($order->tenant_id);

        $issues = $this->providerReadinessService->issuesForDocument(
            $tenant,
            (string) $document->document_type,
        );

        if ($issues !== []) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_submit_blocked'),
                $issues,
            );
        }

        $response = $this->providerRegistry->forDocument($document)->issue($document);

        $freshDocument = $document->fresh();

        $this->attemptRecorder->record(
            $freshDocument,
            'submit',
            'succeeded',
            [
                'document_uuid' => $freshDocument->uuid,
                'document_type' => $freshDocument->document_type,
                'provider' => $freshDocument->provider,
                'provider_document_id' => $freshDocument->provider_document_id,
            ],
            $response,
        );

        $this->messageRecorder->record(
            $freshDocument,
            'submission',
            __('messages.order.fiscal_provider_submission_recorded'),
            $response,
        );

        return $freshDocument;
    }
}
