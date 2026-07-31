<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class ProductType extends BaseModel
{
    protected $table = 'product_types';

    protected $fillable = [
        'tenant_id',
        'product_category_id',
        'name',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_category_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
