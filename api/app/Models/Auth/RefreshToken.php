<?php

namespace App\Models\Auth;

use App\Models\BaseModel;

class RefreshToken extends BaseModel
{
    protected $table = 'refresh_tokens';

    protected $fillable = [
        'uuid',
        'user_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'user_agent_hash',
        'ip_hash',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['id', 'token_hash', 'user_agent_hash', 'ip_hash', 'user_id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'];
}