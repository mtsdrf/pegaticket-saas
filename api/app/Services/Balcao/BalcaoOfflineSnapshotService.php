<?php

namespace App\Services\Balcao;

use App\Http\Resources\Balcao\ComandaResource;
use App\Http\Resources\Balcao\TableResource;
use App\Models\Balcao\Comanda;
use App\Models\Balcao\Table;
use App\Models\Product\Product;

class BalcaoOfflineSnapshotService
{
    public function build(int $tenantId): array
    {
        $tables = Table::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('label')
            ->get();

        $comandas = Comanda::query()
            ->with(['table', 'items.product', 'items.station'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', [Comanda::STATUS_OPEN, Comanda::STATUS_CLOSING])
            ->orderByDesc('opened_at')
            ->get();

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        return [
            'generated_at' => now()->toIso8601String(),
            'tables' => TableResource::collection($tables)->resolve(),
            'comandas' => ComandaResource::collection($comandas)->resolve(),
            'products' => $products->map(fn (Product $product) => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit' => $product->unit,
                'price' => (float) $product->price,
                'updated_at' => $product->updated_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
