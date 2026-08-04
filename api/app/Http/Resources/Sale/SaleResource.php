<?php

namespace App\Http\Resources\Sale;

use App\Models\Sale\Sale;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'codigo' => $this->codigo,
            'is_installment' => $this->is_installment,
            'total_amount' => $this->total_amount,
            'service_fee' => (float) $this->service_fee,
            'discount_amount' => (float) $this->discount_amount,
            'coupon_code' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'paid_amount' => $this->paid_amount !== null ? (float) $this->paid_amount : null,
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at,
            'due_date' => $this->due_date,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'payment_method' => $this->payment_method,
            'needs_change' => $this->needs_change,
            'change_for_amount' => $this->change_for_amount !== null ? (float) $this->change_for_amount : null,
            'status' => $this->status,
            'origin' => Sale::normalizeOrigin($this->origin),
            // Motor de risco básico (roadmap Fase 7) — só um sinal pro
            // staff revisar manualmente, nunca bloqueia a venda sozinho.
            'risk_flagged' => (bool) $this->risk_flagged,
            'risk_reason' => $this->risk_reason,
            // Rastreio de campanha/afiliado (Fase 6) — só informativo pro
            // staff enxergar de onde veio a venda, nunca exposto pro
            // comprador (PortalSaleResource/SalePublicTrackingResource).
            'affiliate_name' => $this->whenLoaded('affiliate', fn () => $this->affiliate?->name),
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'rating' => $this->rating?->rating,
            'rating_comment' => $this->rating?->comment,
            'final_customer' => $this->whenLoaded('finalCustomer', function () {
                $link = $this->finalCustomerLink;

                return [
                    'uuid' => $this->finalCustomer->uuid,
                    'name' => $this->finalCustomer->name,
                    'last_name' => $this->finalCustomer->last_name,
                    'phone_primary' => $link?->phone_primary,
                    'endereco' => null,
                ];
            }),
            'operator' => $this->whenLoaded('operator', fn () => $this->operator ? [
                'uuid' => $this->operator->uuid,
                'name' => $this->operator->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'uuid' => $item->uuid,
                'ticket_type' => $item->ticketType ? [
                    'uuid' => $item->ticketType->uuid,
                    'name' => $item->ticketType->name,
                    'unit' => $item->ticketType->unit,
                ] : null,
                'event_product' => $item->eventProduct ? [
                    'uuid' => $item->eventProduct->uuid,
                    'name' => $item->eventProduct->name,
                ] : null,
                'seat' => $item->seat ? [
                    'uuid' => $item->seat->uuid,
                    'label' => $item->seat->label,
                    'sector_name' => $item->seat->sector_name,
                    'kind' => $item->seat->kind,
                ] : null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'notes' => $item->notes,
            ])),
            'installments' => $this->when(
                $this->is_installment,
                fn () => $this->whenLoaded('installments', fn () => $this->installments
                    ->sortBy('installment_number')
                    ->values()
                    ->map(fn ($installment) => [
                        'uuid' => $installment->uuid,
                        'installment_number' => $installment->installment_number,
                        'amount' => $installment->amount,
                        'due_date' => $installment->due_date,
                        'is_paid' => $installment->is_paid,
                        'paid_at' => $installment->paid_at,
                    ]))
            ),
            'created_at' => $this->created_at,
        ];
    }
}
