<?php

namespace App\Services\Fiscal;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Arr;

class OrderFiscalDraftBuilder
{
    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    public function build(Order $order, array $preview): array
    {
        $order->loadMissing(['client.endereco.estado', 'items.product']);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->with('endereco.estado')->findOrFail($order->tenant_id);

        $lineItems = collect($preview['line_items'] ?? [])
            ->keyBy(fn (array $item) => $item['product_uuid'] ?? null);

        return [
            'generated_at' => now()->toIso8601String(),
            'issuer' => [
                'tenant_uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'cnpj' => $tenant->cnpj,
                'tax_regime' => $tenant->tax_regime,
                'state_registration' => $tenant->ie,
                'ibge_city_code' => $tenant->ibge_city_code,
                'uf' => $tenant->endereco?->estado?->uf,
            ],
            'recipient' => [
                'client_uuid' => $order->client?->uuid,
                'name' => $order->client?->name,
                'document' => $order->client?->cpf_cnpj,
                'state_registration' => $order->client?->ie,
                'uf' => $order->client?->endereco?->estado?->uf,
            ],
            'operation' => [
                'order_uuid' => $order->uuid,
                'order_code' => $order->codigo,
                'order_origin' => Arr::get($preview, 'context.order_origin'),
                'fulfillment_type' => Arr::get($preview, 'context.fulfillment_type'),
                'destination_type' => Arr::get($preview, 'context.destination_type'),
                'document_type' => Arr::get($preview, 'context.document_type'),
                'operation_profile' => [
                    'uuid' => Arr::get($preview, 'operation_profile.uuid'),
                    'name' => Arr::get($preview, 'operation_profile.name'),
                    'operation_nature' => Arr::get($preview, 'operation_profile.operation_nature'),
                    'default_cfop' => Arr::get($preview, 'operation_profile.default_cfop'),
                ],
            ],
            'items' => $order->items->map(function (OrderItem $item) use ($lineItems) {
                $resolved = $lineItems->get($item->product?->uuid);

                return [
                    'product_uuid' => $item->product?->uuid,
                    'product_name' => $item->product?->name,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                    'cfop' => $resolved['resolved_cfop'] ?? null,
                    'ncm' => $item->product?->ncm,
                    'origin' => $item->product?->origin,
                    'csosn_cst' => $item->product?->csosn_cst,
                    'tax_rules' => $resolved['matched_tax_rules'] ?? [],
                ];
            })->values()->all(),
            'totals' => [
                'items_amount' => (float) $order->items->sum('line_total'),
                'delivery_fee' => (float) $order->delivery_fee,
                'service_fee' => (float) $order->service_fee,
                'discount_amount' => (float) $order->discount_amount,
                'paid_amount' => (float) ($order->paid_amount ?? 0),
                'total_amount' => (float) $order->total_amount,
            ],
            'issues' => Arr::get($preview, 'issues', []),
        ];
    }
}
