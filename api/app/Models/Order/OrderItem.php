<?php

namespace App\Models\Order;

use App\Models\BaseModel;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sem Repository/Service próprios: OrderItem é um objeto de valor
 * inteiramente possuído por Order (sem CRUD/rota independente), criado e
 * lido só através de OrderService. Ver App\Services\Order\OrderService.
 */
class OrderItem extends BaseModel
{
    protected $table = 'order_items';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'order_id',
        'product_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class, 'order_item_id')
            ->orderBy('id');
    }
}
