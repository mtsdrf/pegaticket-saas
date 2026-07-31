<?php

namespace App\Http\Resources\Fiscal;

use Illuminate\Http\Resources\Json\JsonResource;

class FiscalProviderMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'provider_document_id' => $this->provider_document_id,
            'message_type' => $this->message_type,
            'level' => $this->level,
            'provider_status' => $this->provider_status,
            'summary' => $this->summary,
            'payload' => $this->payload,
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
