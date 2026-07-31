<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'action' => $this->action,
            'status' => $this->status,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'executed_at' => optional($this->executed_at)?->toIso8601String(),
            'error_message' => $this->error_message,
        ];
    }
}
