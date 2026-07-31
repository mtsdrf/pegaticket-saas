<?php

namespace App\Http\Resources\Report;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableReportListResource extends JsonResource
{
    public function toArray($request): array
    {
        $dueDate = Carbon::parse($this->due_date)->startOfDay();
        $today = now()->startOfDay();
        $isOverdue = $dueDate->lt($today);

        return [
            'row_key' => $this->source . ':' . ($this->installment_uuid ?? $this->order_uuid),
            'source' => $this->source,
            'source_label' => $this->source === 'installment' ? 'Parcela' : 'Pedido',
            'order_uuid' => $this->order_uuid,
            'installment_uuid' => $this->installment_uuid,
            'client_name' => $this->client_name,
            'client_phone_primary' => $this->client_phone_primary,
            'amount' => (float) $this->amount,
            'due_date' => $dueDate->toDateString(),
            'created_at' => $this->created_at,
            'is_overdue' => $isOverdue,
            'days_overdue' => $isOverdue ? $dueDate->diffInDays($today) : 0,
        ];
    }
}
