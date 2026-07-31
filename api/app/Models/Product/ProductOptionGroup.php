<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionGroup extends BaseModel
{
    protected $table = 'product_option_groups';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'description',
        'kind',
        'min_select',
        'max_select',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_select' => 'integer',
        'max_select' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'product_option_group_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
