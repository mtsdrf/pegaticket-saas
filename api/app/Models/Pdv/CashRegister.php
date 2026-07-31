<?php

namespace App\Models\Pdv;

use App\Models\BaseModel;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends BaseModel
{
    protected $table = 'cash_registers';

    protected $fillable = [
        'tenant_id',
        'stock_location_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'stock_location_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }
}
