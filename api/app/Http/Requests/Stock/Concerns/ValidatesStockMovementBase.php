<?php

namespace App\Http\Requests\Stock\Concerns;

use Illuminate\Validation\Rule;

/**
 * Regras comuns a toda movimentação simples (produto+local+quantidade+
 * motivo): entry, exit, return, loss, block, unblock, reserve. Cada
 * Request continua sendo uma classe própria (rota/validação específica
 * por tipo), isto só evita repetir o mesmo bloco de regras 7 vezes.
 */
trait ValidatesStockMovementBase
{
    protected function baseMovementRules(): array
    {
        return [
            'product_uuid' => [
                'required',
                'uuid',
                Rule::exists('products', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'location_uuid' => [
                'required',
                'uuid',
                Rule::exists('stock_locations', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function baseMovementMessages(): array
    {
        return [
            'product_uuid.exists' => __('messages.stock.invalid_product'),
            'location_uuid.exists' => __('messages.stock.invalid_location'),
        ];
    }
}
