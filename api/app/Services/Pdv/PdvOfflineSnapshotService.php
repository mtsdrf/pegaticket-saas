<?php

namespace App\Services\Pdv;

use App\Http\Resources\Pdv\CashSessionResource;
use App\Models\Pdv\CashSession;
use App\Models\Product\Product;

class PdvOfflineSnapshotService
{
    private const OFFLINE_ALLOWED_PAYMENT_METHODS = ['cash'];

    private const OFFLINE_BLOCKED_PAYMENT_METHODS = ['pix', 'credit', 'debit'];

    public function build(int $tenantId): array
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('is_available', true)
            ->withSum('stockBalances', 'quantity_on_hand')
            ->orderBy('name')
            ->get();

        $session = CashSession::query()
            ->with('cashRegister')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'offline_payment_methods' => self::OFFLINE_ALLOWED_PAYMENT_METHODS,
            'blocked_payment_methods' => self::OFFLINE_BLOCKED_PAYMENT_METHODS,
            'cash_session' => $session ? CashSessionResource::make($session)->resolve() : null,
            'products' => $products->map(fn (Product $product) => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit' => $product->unit,
                'price' => (float) $product->price,
                'stock_quantity' => (float) ($product->stock_balances_sum_quantity_on_hand ?? 0),
                'updated_at' => $product->updated_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
