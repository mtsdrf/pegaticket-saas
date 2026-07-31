<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use App\Models\Client\ClientCategory;

class ProductCategoryPrice extends BaseModel
{
    protected $table = 'product_category_prices';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'client_category_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_id',
        'client_category_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function clientCategory()
    {
        return $this->belongsTo(ClientCategory::class);
    }
}
