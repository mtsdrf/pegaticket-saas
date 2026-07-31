<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use App\Models\Stock\StockBalance;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `stock_quantity` é um campo legado (era o único controle de estoque
 * antes do módulo de Estoque existir). Não é mais gravável via API — a
 * fonte de verdade de saldo é `StockBalance.quantity_on_hand`, somado por
 * produto via `stockBalances()`. Ver ProductResource/ProductService.
 */
class Product extends BaseModel
{
    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'product_type_id',
        'name',
        'sku',
        'barcode',
        'brand',
        'ncm',
        'cest',
        'origin',
        'default_cfop',
        'csosn_cst',
        'unit',
        'price',
        'wholesale_min_quantity',
        'wholesale_price',
        'description',
        'image_path',
        'image_data',
        'image_mime',
        'image_updated_at',
        'is_available',
        'surcharge_rate',
        'is_lot_controlled',
        'is_expiry_controlled',
        'is_serial_controlled',
        'min_stock',
        'max_stock',
        'reorder_point',
        'reorder_qty',
        'last_purchase_cost',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'image_updated_at' => 'datetime',
        'wholesale_min_quantity' => 'decimal:3',
        'wholesale_price' => 'decimal:2',
        'is_available' => 'boolean',
        'stock_quantity' => 'integer',
        'surcharge_rate' => 'decimal:2',
        'is_lot_controlled' => 'boolean',
        'is_expiry_controlled' => 'boolean',
        'is_serial_controlled' => 'boolean',
        'min_stock' => 'decimal:3',
        'max_stock' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'reorder_qty' => 'decimal:3',
        'last_purchase_cost' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_type_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class, 'product_id');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
