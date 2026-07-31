<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\FiscalDocument;
use App\Models\Fiscal\FiscalProviderMessage;

class FiscalProviderMessageRecorder
{
    /**
     * @param array<string, mixed> $payload
     */
    public function record(
        FiscalDocument $document,
        string $messageType,
        string $summary,
        array $payload = [],
        string $level = 'info',
    ): FiscalProviderMessage {
        return FiscalProviderMessage::create([
            'tenant_id' => $document->tenant_id,
            'fiscal_document_id' => $document->id,
            'provider' => (string) ($payload['provider'] ?? $document->provider ?? 'manual'),
            'provider_document_id' => $payload['provider_document_id'] ?? $document->provider_document_id,
            'message_type' => $messageType,
            'level' => $level,
            'provider_status' => $payload['status'] ?? $document->status,
            'summary' => $summary,
            'payload' => $payload === [] ? null : $payload,
            'received_at' => now(),
        ]);
    }
}
