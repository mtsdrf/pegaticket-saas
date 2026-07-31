<?php

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalProviderInterface;
use App\Models\Fiscal\FiscalDocument;

/**
 * Adapter provisório para providers fiscais configuráveis enquanto a emissão
 * real ainda não conversa com SEFAZ/prefeitura. Ele preserva o fluxo
 * operacional do rascunho, mas grava no documento qual provider alvo foi
 * configurado pela empresa.
 */
class DraftOnlyFiscalProvider implements FiscalProviderInterface
{
    public function __construct(
        private readonly string $providerSlug,
    ) {
    }

    public function issue(FiscalDocument $document): array
    {
        $providerDocumentId = strtoupper($this->providerSlug) . '-' . $document->uuid;

        $document->fill([
            'provider' => $this->providerSlug,
            'provider_document_id' => $providerDocumentId,
            'status' => 'provider_submitted',
            'submitted_at' => now(),
        ])->save();

        return [
            'fiscal_document_uuid' => $document->uuid,
            'status' => $document->status,
            'provider' => $document->provider,
            'provider_document_id' => $providerDocumentId,
        ];
    }

    public function cancel(FiscalDocument $document, string $reason): array
    {
        $document->fill([
            'provider' => $this->providerSlug,
            'status' => 'canceled',
            'canceled_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return [
            'fiscal_document_uuid' => $document->uuid,
            'status' => $document->status,
            'provider' => $document->provider,
        ];
    }

    public function getStatus(string $providerDocumentId): array
    {
        return [
            'provider_document_id' => $providerDocumentId,
            'provider' => $this->providerSlug,
            'status' => 'pending',
            'message' => 'Documento recebido pelo provider configurado e aguardando integração fiscal real.',
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
