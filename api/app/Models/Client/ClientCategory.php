<?php

namespace App\Models\Client;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class ClientCategory extends BaseModel
{
    protected $table = 'client_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
    ];

    protected $casts = [
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
