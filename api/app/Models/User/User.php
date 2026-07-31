<?php

namespace App\Models\User;

use App\Models\BaseAuthenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends BaseAuthenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'avatar_path',
        'avatar_data',
        'avatar_mime',
        'avatar_updated_at',
        'pending_email',
        'pending_email_token_hash',
        'pending_email_expires_at',
        'password_reset_token_hash',
        'password_reset_expires_at',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = [
        'id',
        'password',
        'remember_token',
        'pending_email_token_hash',
        'password_reset_token_hash',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'avatar_updated_at' => 'datetime',
        'pending_email_expires_at' => 'datetime',
        'password_reset_expires_at' => 'datetime',
    ];

    public function groups()
    {
        return $this->belongsToMany(\App\Models\Group\Group::class, 'group_user')
            ->using(\App\Models\Group\GroupUser::class)
            ->withTimestamps()
            ->wherePivotNull('deleted_at')
            ->withPivot(['uuid', 'created_by', 'updated_by', 'deleted_by', 'deleted_at']);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['uuid' => $this->uuid];
    }
}
