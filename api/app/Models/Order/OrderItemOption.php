<?php

namespace App\Models\Order;

use App\Models\BaseModel;
use App\Models\Product\ProductOption;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemOption extends BaseModel
{
    protected $table = 'order_item_options';

    protected $fillable = [
        'tenant_id',
        'order_item_id',
        'product_option_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'order_item_id',
        'product_option_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}
