<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class TenantRole extends BaseModel
{
    protected $table = 'tenant_roles';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
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

    public function permissions()
    {
        return $this->hasMany(TenantRolePermission::class, 'tenant_role_id')
            ->whereNull('deleted_at');
    }
}
