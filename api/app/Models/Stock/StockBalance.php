<?php

namespace App\Models\Stock;

use App\Models\BaseModel;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;

/**
 * quantity_available NÃO é uma coluna real — é sempre computada
 * (quantity_on_hand - quantity_reserved - quantity_blocked) para evitar
 * saldo persistido divergente. Ver getQuantityAvailableAttribute().
 *
 * quantity_on_hand/reserved/blocked são decimal(12,3) (Fase 8: quantidade
 * fracionária real, produto vendido por peso). O cast decimal:3 do Eloquent
 * devolve string — getQuantityAvailableAttribute() converte para float
 * antes de subtrair (precisão de float é suficiente aqui, valor não é
 * monetário, só 3 casas decimais).
 */
class StockBalance extends BaseModel
{
    protected $table = 'stock_balances';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'location_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_blocked',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'quantity_reserved' => 'decimal:3',
        'quantity_blocked' => 'decimal:3',
    ];

    protected $appends = [
        'quantity_available',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function getQuantityAvailableAttribute(): float
    {
        return (float) $this->quantity_on_hand - (float) $this->quantity_reserved - (float) $this->quantity_blocked;
    }
}
