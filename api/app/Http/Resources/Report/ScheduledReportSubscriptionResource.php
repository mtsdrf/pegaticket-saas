<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledReportSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'recipient_email' => $this->recipient_email,
            'frequency' => $this->frequency,
            'last_sent_at' => $this->last_sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
