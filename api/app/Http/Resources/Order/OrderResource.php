<?php

namespace App\Http\Resources\Order;

use App\Http\Resources\Fiscal\FiscalDocumentResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'codigo' => $this->codigo,
            'is_installment' => $this->is_installment,
            'total_amount' => $this->total_amount,
            'delivery_fee' => (float) $this->delivery_fee,
            'service_fee' => (float) $this->service_fee,
            'discount_amount' => (float) $this->discount_amount,
            'coupon_code' => $this->whenLoaded('coupon', fn() => $this->coupon?->code),
            'paid_amount' => $this->paid_amount !== null ? (float) $this->paid_amount : null,
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at,
            'is_delivered' => $this->is_delivered,
            'delivered_at' => $this->delivered_at,
            'due_date' => $this->due_date,
            'expected_delivery_date' => $this->expected_delivery_date,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'payment_method' => $this->payment_method,
            'needs_change' => $this->needs_change,
            'change_for_amount' => $this->change_for_amount !== null ? (float) $this->change_for_amount : null,
            'status' => $this->status,
            'origin' => $this->origin,
            'fulfillment_type' => $this->fulfillment_type,
            'is_out_for_delivery' => $this->is_out_for_delivery,
            'out_for_delivery_at' => $this->out_for_delivery_at,
            'rating' => $this->rating?->rating,
            'rating_comment' => $this->rating?->comment,
            'fiscal_document' => $this->whenLoaded(
                'latestFiscalDocument',
                fn() => $this->latestFiscalDocument
                    ? new FiscalDocumentResource($this->latestFiscalDocument)
                    : null
            ),
            'client' => $this->whenLoaded('client', fn() => [
                'uuid' => $this->client->uuid,
                'name' => $this->client->name,
                'last_name' => $this->client->last_name,
                'phone_primary' => $this->client->phone_primary,
                'endereco' => $this->client->endereco ? [
                    'logradouro' => $this->client->endereco->logradouro,
                    'numero' => $this->client->endereco->numero,
                    'complemento' => $this->client->endereco->complemento,
                    'cep' => $this->client->endereco->cep,
                    'bairro_name' => $this->client->endereco->bairro?->name,
                    'cidade_name' => $this->client->endereco->cidade?->name,
                ] : null,
            ]),
            'stock_location' => $this->whenLoaded('stockLocation', fn() => [
                'uuid' => $this->stockLocation->uuid,
                'name' => $this->stockLocation->name,
            ]),
            'operator' => $this->whenLoaded('operator', fn() => $this->operator ? [
                'uuid' => $this->operator->uuid,
                'name' => $this->operator->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'uuid' => $item->uuid,
                'product' => [
                    'uuid' => $item->product->uuid,
                    'name' => $item->product->name,
                    'unit' => $item->product->unit,
                ],
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'notes' => $item->notes,
                'options_total' => (float) $item->options->sum(fn($option) => (float) $option->line_total),
                'options' => $item->options->map(fn($option) => [
                    'uuid' => $option->uuid,
                    'quantity' => $option->quantity,
                    'unit_price' => $option->unit_price,
                    'line_total' => $option->line_total,
                    'product_option' => [
                        'uuid' => $option->productOption->uuid,
                        'name' => $option->productOption->name,
                        'description' => $option->productOption->description,
                        'group_name' => $option->productOption->group?->name,
                    ],
                ])->values(),
            ])),
            'installments' => $this->when(
                $this->is_installment,
                fn() => $this->whenLoaded('installments', fn() => $this->installments
                    ->sortBy('installment_number')
                    ->values()
                    ->map(fn($installment) => [
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
