<?php

namespace App\Models\Stock;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class StockLocation extends BaseModel
{
    protected $table = 'stock_locations';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'address',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
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
}
