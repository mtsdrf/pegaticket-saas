<?php

namespace App\Http\Resources\Webhook;

use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'event_type' => $this->event_type,
            'response_status' => $this->response_status,
            'success' => $this->success,
            'attempt' => $this->attempt,
            'error' => $this->error,
            'attempted_at' => optional($this->attempted_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
