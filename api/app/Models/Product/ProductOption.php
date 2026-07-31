<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use App\Models\Order\OrderItemOption;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends BaseModel
{
    protected $table = 'product_options';

    protected $fillable = [
        'tenant_id',
        'product_option_group_id',
        'name',
        'description',
        'price',
        'sort_order',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sort_order' => 'integer',
        'is_available' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_option_group_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function orderItemOptions(): HasMany
    {
        return $this->hasMany(OrderItemOption::class, 'product_option_id');
    }
}
