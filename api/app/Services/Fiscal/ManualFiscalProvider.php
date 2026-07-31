<?php

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalProviderInterface;
use App\Models\Fiscal\FiscalDocument;

/**
 * Adapter fiscal manual (roadmap Fiscal D0) — NÃO chama SEFAZ/prefeitura,
 * não usa certificado digital, não gera XML nem SOAP de verdade.
 *
 * issue() apenas cria/atualiza o registro com status='provider_submitted' e
 * provider='manual'; NUNCA autoriza uma nota de verdade. É o ponto de troca
 * futuro: quando a emissão real for plugada, basta um novo adapter
 * implementando FiscalProviderInterface (ex: usando a lib `sped-nfe` ou um
 * serviço pago de emissão) e trocar o binding em AppServiceProvider, sem
 * mudar a tabela fiscal_documents nem quem consome a interface.
 */
class ManualFiscalProvider implements FiscalProviderInterface
{
    public function issue(FiscalDocument $document): array
    {
        $providerDocumentId = $document->provider_document_id ?: 'MANUAL-' . $document->uuid;

        // Sem emissão real: o documento só registra a submissão lógica ao
        // provider. Nunca vira 'authorized' por aqui.
        $document->fill([
            'provider' => 'manual',
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
            'status' => 'canceled',
            'canceled_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return [
            'fiscal_document_uuid' => $document->uuid,
            'status' => $document->status,
        ];
    }

    public function getStatus(string $providerDocumentId): array
    {
        // Sem provedor real, nada a consultar externamente.
        return [
            'provider_document_id' => $providerDocumentId,
            'provider' => 'manual',
            'status' => 'pending',
            'message' => 'Documento recebido pelo provider manual e aguardando emissão real.',
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
