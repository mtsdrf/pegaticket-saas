<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AccountingMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'sender_type' => $this->sender_type,
            'body' => $this->body,
            'due_date' => optional($this->due_date)->toDateString(),
            'status' => $this->status,
            'attachment_name' => $this->attachment_name,
            'attachment_url' => $this->attachment_path
                ? Storage::disk('public')->url($this->attachment_path)
                : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
