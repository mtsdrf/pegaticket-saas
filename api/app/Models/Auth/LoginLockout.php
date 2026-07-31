<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class LoginLockout extends Model
{
    protected $table = 'login_lockouts';

    protected $fillable = [
        'email',
        'failed_attempts',
        'locked_until',
    ];

    protected $casts = [
        'failed_attempts' => 'integer',
        'locked_until' => 'datetime',
    ];
}
