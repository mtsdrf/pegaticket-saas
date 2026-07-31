<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\User\User;

class TenantUser extends BaseModel
{
    protected $table = 'tenant_users';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'tenant_role_id',
        'is_active',
        'onboarding_checklist_dismissed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'onboarding_checklist_dismissed_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'user_id',
        'tenant_role_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id');
    }
}
