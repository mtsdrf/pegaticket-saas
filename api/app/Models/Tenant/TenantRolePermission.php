<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Permission\Action;
use App\Models\Functionality\Functionality;

class TenantRolePermission extends BaseModel
{
    protected $table = 'tenant_role_permissions';

    protected $fillable = [
        'tenant_role_id',
        'functionality_id',
        'action_id',
        'discount_limit_percent',
    ];

    protected $casts = [
        'discount_limit_percent' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_role_id',
        'functionality_id',
        'action_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function functionality()
    {
        return $this->belongsTo(Functionality::class);
    }

    public function action()
    {
        return $this->belongsTo(Action::class);
    }

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id');
    }
}