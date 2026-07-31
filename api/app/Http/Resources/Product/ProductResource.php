<?php

namespace App\Http\Resources\Product;

use App\Services\Permission\PermissionService;
use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'ncm' => $this->ncm,
            'cest' => $this->cest,
            'origin' => $this->origin,
            'default_cfop' => $this->default_cfop,
            'csosn_cst' => $this->csosn_cst,
            'unit' => $this->unit,
            'price' => (float) $this->price,
            'description' => $this->description,
            'image_url' => MediaUrl::resolvePublic(
                $this->image_path,
                $this->image_data ?? $this->image_mime,
                '/api/v1/products/' . $this->uuid . '/image',
                $this->image_updated_at,
                'product'
            ),
            'is_available' => $this->is_available,
            // Derivado da soma de StockBalance.quantity_on_hand por produto
            // (via withSum/loadSum('stockBalances', 'quantity_on_hand') nas
            // camadas que montam este Resource) — products.stock_quantity é
            // campo legado, não é mais fonte de verdade nem gravável via API.
            // (float), não (int): quantity_on_hand é decimal(12,3) desde a
            // Fase 8 (produto vendido por peso), truncar pra int perderia a
            // fração real do saldo.
            'stock_quantity' => (float) ($this->stock_balances_sum_quantity_on_hand ?? 0),
            'surcharge_rate' => $this->surcharge_rate !== null ? (float) $this->surcharge_rate : null,
            'is_lot_controlled' => $this->is_lot_controlled,
            'is_expiry_controlled' => $this->is_expiry_controlled,
            'is_serial_controlled' => $this->is_serial_controlled,
            'min_stock' => $this->min_stock !== null ? (float) $this->min_stock : null,
            'max_stock' => $this->max_stock !== null ? (float) $this->max_stock : null,
            'reorder_point' => $this->reorder_point !== null ? (float) $this->reorder_point : null,
            'reorder_qty' => $this->reorder_qty !== null ? (float) $this->reorder_qty : null,
            'product_type' => $this->whenLoaded('productType', fn() => [
                'uuid' => $this->productType->uuid,
                'name' => $this->productType->name,
                'product_category' => $this->productType->relationLoaded('productCategory') && $this->productType->productCategory ? [
                    'uuid' => $this->productType->productCategory->uuid,
                    'name' => $this->productType->productCategory->name,
                ] : null,
            ]),
            'option_groups' => $this->whenLoaded('optionGroups', fn () => $this->optionGroups->map(fn ($group) => [
                'uuid' => $group->uuid,
                'name' => $group->name,
                'description' => $group->description,
                'kind' => $group->kind,
                'min_select' => $group->min_select,
                'max_select' => $group->max_select,
                'sort_order' => $group->sort_order,
                'is_active' => $group->is_active,
                'options' => $group->relationLoaded('options')
                    ? $group->options->map(fn ($option) => [
                        'uuid' => $option->uuid,
                        'name' => $option->name,
                        'description' => $option->description,
                        'price' => (float) $option->price,
                        'sort_order' => $option->sort_order,
                        'is_available' => $option->is_available,
                    ])->values()
                    : [],
            ])->values()),
            'created_at' => $this->created_at,
        ];

        // view_costs: last_purchase_cost (e qualquer campo de custo futuro)
        // só aparece na resposta com a permissão — chave omitida por
        // completo quando ausente, nunca retornada como null. Primeira
        // visibilidade condicional de campo por permissão do projeto, ver
        // architecture-decisions.md.
        if (Auth::id() && app(PermissionService::class)->userCanViaGroups(Auth::id(), 'stock', 'view_costs')) {
            $data['last_purchase_cost'] = $this->last_purchase_cost !== null ? (float) $this->last_purchase_cost : null;
        }

        return $data;
    }
}
