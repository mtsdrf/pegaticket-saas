<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource dedicado à rota pública GET /rastreio/{sale:uuid} (Fase 5.1 do
 * roadmap — link de acompanhamento sem login enviado por WhatsApp).
 * Allow-list deliberada: NUNCA reaproveitar SaleResource aqui nem
 * adicionar campo novo sem confirmação explícita — é rota pública, todo
 * campo extra é superfície de vazamento. Nunca expor `notes`/
 * `cancellation_reason` (podem conter observação interna da equipe),
 * `final_customer.uuid`/`final_customer.phone`, `stock_location`,
 * `created_by/updated_by` nem uuid de items/installments.
 */
class SalePublicTrackingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'tenant_slug' => $this->whenLoaded('tenant', fn() => $this->tenant->slug),
            'tenant_name' => $this->whenLoaded('tenant', fn() => $this->tenant->name),
            'final_customer_name' => $this->whenLoaded('finalCustomer', fn() => $this->finalCustomer->name),
            'is_installment' => $this->is_installment,
            'total_amount' => $this->total_amount,
            'discount_amount' => (float) $this->discount_amount,
            'coupon_code' => $this->whenLoaded('coupon', fn() => $this->coupon?->code),
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at,
            // Fila de aprovação da loja (Delivery Fase 1) — expor cru
            // (pending_approval|confirmed|rejected) permite o frontend
            // distinguir "aguardando aprovação"/"recusado" de "em
            // preparação"; antes só existia is_cancelled (cancelled_at !==
            // null), que nunca cobria status=rejected (venda recusado
            // aparecia pro cliente como "em preparação" — bug real).
            'status' => $this->status,
            'is_cancelled' => $this->cancelled_at !== null,
            'latest_payment' => $this->whenLoaded('latestPayment', fn() => $this->latestPayment ? [
                'status' => $this->latestPayment->status,
                'provider_status' => $this->latestPayment->metadata['provider_status'] ?? $this->latestPayment->metadata['raw_status'] ?? null,
                'method' => $this->latestPayment->method,
                'paid_at' => $this->latestPayment->paid_at,
            ] : null),
            'created_at' => $this->created_at,
            'purchase_event_url' => $this->when(
                $this->resource->relationLoaded('tenant') && $this->resource->relationLoaded('items'),
                function () {
                    $firstEventSlug = $this->items
                        ->map(fn ($item) => $item->ticketType?->event?->slug ?? $item->eventProduct?->event?->slug)
                        ->filter()
                        ->first();

                    if (! $firstEventSlug || ! $this->tenant?->slug) {
                        return null;
                    }

                    return rtrim((string) config('app.frontend_url'), '/').'/eventos/'.$this->tenant->slug.'/'.$firstEventSlug;
                }
            ),
            'ticket_pdf_url' => $this->when(
                (bool) $this->is_paid,
                fn () => url('/api/v1/rastreio/'.$this->uuid.'/ingressos.pdf')
            ),
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'ticket_type_name' => $item->ticketType?->name ?? $item->eventProduct?->name,
                'seat_label' => $item->seat?->label,
                'seat_sector_name' => $item->seat?->sector_name,
                'seat_kind' => $item->seat?->kind,
                'quantity' => $item->quantity,
                // Não sensível (rótulo de exibição, ex. "un"/"kg") — usado
                // só pra decidir se a quantidade mostra casas decimais.
                'unit' => $item->ticketType?->unit,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'notes' => $item->notes,
            ])),
            'installments' => $this->when(
                $this->is_installment,
                fn() => $this->whenLoaded('installments', fn() => $this->installments
                    ->sortBy('installment_number')
                    ->values()
                    ->map(fn($installment) => [
                        'installment_number' => $installment->installment_number,
                        'amount' => $installment->amount,
                        'due_date' => $installment->due_date,
                        'is_paid' => $installment->is_paid,
                        'paid_at' => $installment->paid_at,
                    ])),
                []
            ),
        ];
    }
}
