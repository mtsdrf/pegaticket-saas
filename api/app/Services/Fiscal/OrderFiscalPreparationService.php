<?php

namespace App\Services\Fiscal;

use App\Exceptions\Fiscal\FiscalPreparationBlockedException;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderFiscalPreparationService
{
    public function __construct(
        private OrderFiscalPreviewService $previewService,
        private OrderFiscalDraftBuilder $draftBuilder,
        private FiscalDocumentNumberingService $numberingService,
        private FiscalDocumentLifecycleService $lifecycleService,
        private FiscalProviderRegistry $providerRegistry,
        private FiscalProviderReadinessService $providerReadinessService,
    ) {
    }

    public function prepare(Order $order): FiscalDocument
    {
        $this->lifecycleService->assertOrderCanPrepare($order);
        $preview = $this->previewService->preview($order);

        if (!($preview['can_prepare'] ?? false)) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_prepare_blocked'),
                $preview['issues'] ?? [],
            );
        }

        $providerIssues = $this->providerReadinessService->issuesForDocument(
            Tenant::query()->findOrFail($order->tenant_id),
            (string) ($preview['context']['document_type'] ?? ''),
        );

        if ($providerIssues !== []) {
            throw new FiscalPreparationBlockedException(
                __('messages.order.fiscal_prepare_blocked'),
                $providerIssues,
            );
        }

        return DB::transaction(function () use ($order, $preview) {
            $payloadSnapshot = $this->draftBuilder->build($order, $preview);
            $providerSlug = $this->providerRegistry->providerSlug(
                Tenant::query()->findOrFail($order->tenant_id)
            );

            /** @var FiscalDocument|null $document */
            $document = $order->fiscalDocuments()
                ->whereIn('status', ['draft', 'authorized'])
                ->latest('id')
                ->first();

            if (!$document) {
                $document = $order->fiscalDocuments()->create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $order->tenant_id,
                    'document_type' => $preview['context']['document_type'],
                    'status' => 'draft',
                    'provider' => $providerSlug,
                    'payload_snapshot' => $payloadSnapshot,
                ]);
            } else {
                $document->fill([
                    'document_type' => $preview['context']['document_type'],
                    'provider' => $providerSlug,
                    'status' => 'draft',
                    'provider_document_id' => null,
                    'submitted_at' => null,
                    'authorized_at' => null,
                    'rejected_at' => null,
                    'canceled_at' => null,
                    'rejection_reason' => null,
                    'payload_snapshot' => $payloadSnapshot,
                ])->save();
            }

            return $this->numberingService->assignDraftSequence($document)->fresh();
        });
    }
}
