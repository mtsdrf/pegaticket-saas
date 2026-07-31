<?php

namespace App\Http\Resources\Fiscal;

use Illuminate\Http\Resources\Json\JsonResource;

class FiscalDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'document_type' => $this->document_type,
            'series' => $this->series,
            'document_number' => $this->document_number,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_document_id' => $this->provider_document_id,
            'access_key' => $this->access_key,
            'pdf_path' => $this->pdf_path,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'provider_status_checked_at' => $this->provider_status_checked_at?->toIso8601String(),
            'authorized_at' => $this->authorized_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'payload_snapshot_summary' => $this->payload_snapshot ? [
                'generated_at' => data_get($this->payload_snapshot, 'generated_at'),
                'order_code' => data_get($this->payload_snapshot, 'operation.order_code'),
                'operation_profile_name' => data_get($this->payload_snapshot, 'operation.operation_profile.name'),
                'items_count' => count((array) data_get($this->payload_snapshot, 'items', [])),
                'issues_count' => count((array) data_get($this->payload_snapshot, 'issues', [])),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
