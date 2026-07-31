<?php

namespace App\Models\Storefront;

use App\Models\BaseModel;
use App\Models\Location\Bairro;
use App\Models\Tenant\Tenant;

class StoreDeliveryFee extends BaseModel
{
    protected $table = 'store_delivery_fees';

    protected $fillable = [
        'tenant_id',
        'bairro_id',
        'fee',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'bairro_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bairro()
    {
        return $this->belongsTo(Bairro::class);
    }
}
