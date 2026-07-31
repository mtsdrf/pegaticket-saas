<?php

namespace App\Http\Resources\Fiscal;

use Illuminate\Http\Resources\Json\JsonResource;

class FiscalDocumentAttemptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'operation_type' => $this->operation_type,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'idempotency_key' => $this->idempotency_key,
            'payload_hash' => $this->payload_hash,
            'response_hash' => $this->response_hash,
            'attempt_number' => $this->attempt_number,
            'payload' => $this->payload,
            'response_payload' => $this->response_payload,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
