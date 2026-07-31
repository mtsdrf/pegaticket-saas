<?php

namespace App\Http\Resources\Fiscal;

class FiscalDocumentDetailResource extends FiscalDocumentResource
{
    public function toArray($request): array
    {
        return [
            ...parent::toArray($request),
            'payload_snapshot' => $this->payload_snapshot,
            'provider_response_payload' => $this->provider_response_payload,
            'provider_messages' => FiscalProviderMessageResource::collection($this->whenLoaded('providerMessages')),
            'attempts' => FiscalDocumentAttemptResource::collection($this->whenLoaded('attempts')),
        ];
    }
}
