<?php

namespace App\Models\Fiscal;

use App\Models\BaseModel;

class TaxRule extends BaseModel
{
    protected $table = 'tax_rules';

    protected $fillable = [
        'tenant_id',
        'tax_type',
        'rate_percent',
        'scope',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:4',
        'scope' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
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
}
