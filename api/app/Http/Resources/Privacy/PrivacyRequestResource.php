<?php

namespace App\Http\Resources\Privacy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrivacyRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'requester_name' => $this->requester_name,
            'requester_email' => $this->requester_email,
            'requester_role' => $this->requester_role,
            'request_type' => $this->request_type,
            'channel' => $this->channel,
            'status' => $this->status,
            'subject' => $this->subject,
            'description' => $this->description,
            'resolution_notes' => $this->resolution_notes,
            'requested_at' => optional($this->requested_at)->toIso8601String(),
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'requested_by_user' => $this->requesterUser ? [
                'uuid' => $this->requesterUser->uuid,
                'name' => $this->requesterUser->name,
                'email' => $this->requesterUser->email,
            ] : null,
        ];
    }
}
