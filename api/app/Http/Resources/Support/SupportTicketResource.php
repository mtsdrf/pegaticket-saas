<?php

namespace App\Http\Resources\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SupportTicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'attachment_name' => $this->attachment_name,
            'attachment_url' => $this->attachment_path
                ? Storage::disk('public')->url($this->attachment_path)
                : null,
            'diagnostics' => $this->diagnostics,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
