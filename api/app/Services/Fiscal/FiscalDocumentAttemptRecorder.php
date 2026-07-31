<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\FiscalDocument;
use App\Models\Fiscal\FiscalDocumentAttempt;
use Illuminate\Support\Str;

class FiscalDocumentAttemptRecorder
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $responsePayload
     */
    public function record(
        FiscalDocument $document,
        string $operationType,
        string $status,
        array $payload = [],
        array $responsePayload = [],
    ): FiscalDocumentAttempt {
        $attemptNumber = FiscalDocumentAttempt::query()
            ->where('fiscal_document_id', $document->id)
            ->where('operation_type', $operationType)
            ->count() + 1;

        $providerReference = $responsePayload['provider_document_id']
            ?? $payload['provider_document_id']
            ?? $document->provider_document_id;

        return FiscalDocumentAttempt::create([
            'tenant_id' => $document->tenant_id,
            'fiscal_document_id' => $document->id,
            'operation_type' => $operationType,
            'status' => $status,
            'provider' => $responsePayload['provider'] ?? $payload['provider'] ?? $document->provider,
            'provider_reference' => $providerReference,
            'idempotency_key' => (string) Str::uuid(),
            'payload_hash' => $payload === [] ? null : hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'response_hash' => $responsePayload === [] ? null : hash('sha256', json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'attempt_number' => $attemptNumber,
            'payload' => $payload === [] ? null : $payload,
            'response_payload' => $responsePayload === [] ? null : $responsePayload,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
