<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;

class TenantUserInvite extends BaseModel
{
    protected $table = 'tenant_user_invites';

    protected $fillable = [
        'tenant_id',
        'tenant_role_id',
        'name',
        'email',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'tenant_role_id',
        'token_hash',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
