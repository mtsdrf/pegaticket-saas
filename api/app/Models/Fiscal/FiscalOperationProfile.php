<?php

namespace App\Models\Fiscal;

use App\Models\BaseModel;

class FiscalOperationProfile extends BaseModel
{
    protected $table = 'fiscal_operation_profiles';

    protected $fillable = [
        'tenant_id',
        'name',
        'operation_nature',
        'document_type',
        'default_cfop',
        'scope',
        'description',
        'is_active',
    ];

    protected $casts = [
        'scope' => 'array',
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
