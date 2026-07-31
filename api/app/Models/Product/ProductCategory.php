<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class ProductCategory extends BaseModel
{
    protected $table = 'product_categories';

    protected $fillable = [
        'tenant_id',
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
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function productTypes()
    {
        return $this->hasMany(ProductType::class);
    }
}
