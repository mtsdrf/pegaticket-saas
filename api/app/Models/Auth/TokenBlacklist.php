<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class TokenBlacklist extends Model
{
    protected $table = 'token_blacklists';
    protected $fillable = ['jti', 'user_id', 'expires_at', 'reason'];
    protected $casts = ['expires_at' => 'datetime'];
}